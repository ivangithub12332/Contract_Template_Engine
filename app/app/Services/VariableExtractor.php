<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class VariableExtractor
{
    public function normalizeDocxPlaceholders(string $absolutePath, string $format): void
    {
        if ($format !== 'docx') {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Не удалось открыть файл шаблона.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new RuntimeException('Файл шаблона повреждён или не является docx.');
        }

        $normalizedXml = preg_replace_callback(
            '/\{\{(?:(?!\}\}).)*\}\}/s',
            function (array $match): string {
                $plainPlaceholder = html_entity_decode(strip_tags($match[0]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $plainPlaceholder = preg_replace('/\s+/', '', $plainPlaceholder);

                if (! preg_match('/^\{\{\/?[A-Za-z0-9_]+\}\}$/', $plainPlaceholder)) {
                    return $match[0];
                }

                return $plainPlaceholder;
            },
            $xml
        );

        if ($normalizedXml === null || $normalizedXml === $xml) {
            $zip->close();

            return;
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $normalizedXml);
        $zip->close();
    }

    /**
     * Extract unique placeholder keys ({{key}} for docx, AcroForm field names for pdf),
     * in order of first appearance.
     *
     * @return string[]
     */
    public function extractKeys(string $absolutePath, string $format): array
    {
        return $format === 'docx'
            ? $this->extractKeysFromDocx($absolutePath)
            : array_keys($this->inspectPdfFields($absolutePath));
    }

    /**
     * @return array<string, array{type: 'boolean'|'table', columns: string[]}>
     */
    public function inspectDocxBlocks(string $absolutePath, string $format): array
    {
        if ($format !== 'docx') {
            return [];
        }

        $text = $this->readPlainText($absolutePath);
        $blocks = [];

        preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/s', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1];
            preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}/', $match[2], $innerMatches);

            $columns = array_values(array_unique(array_filter(
                $innerMatches[1] ?? [],
                fn (string $innerKey) => $innerKey !== $key
            )));

            $blocks[$key] = [
                'type' => $this->looksLikeBooleanBlock($key) ? 'boolean' : 'table',
                'columns' => $columns,
            ];
        }

        return $blocks;
    }

    /**
     * Check the template for structural problems before it can be published/extracted:
     * docx -> unmatched braces, empty placeholders; pdf -> presence of at least one form field.
     *
     * @return string[] human-readable validation errors, empty when the markup is valid
     */
    public function validate(string $absolutePath, string $format): array
    {
        if ($format !== 'docx') {
            return $this->inspectPdfFields($absolutePath) === []
                ? ['В pdf-шаблоне не найдено ни одного поля формы (AcroForm).']
                : [];
        }

        $text = $this->readPlainText($absolutePath);
        $errors = [];

        $openCount = substr_count($text, '{{');
        $closeCount = substr_count($text, '}}');
        if ($openCount !== $closeCount) {
            $errors[] = 'Разметка не парная: количество "{{" не совпадает с количеством "}}".';
        }

        if (preg_match('/\{\{\s*\}\}/', $text)) {
            $errors[] = 'Найден пустой плейсхолдер "{{}}".';
        }

        return $errors;
    }

    /**
     * Does the template mark {{key}} ... {{/key}} as a block (conditional or repeating)?
     * Only meaningful for docx — static pdf forms have no equivalent concept.
     */
    public function hasBlock(string $absolutePath, string $key): bool
    {
        return str_contains($this->readPlainText($absolutePath), '{{/'.$key.'}}');
    }

    /**
     * Read a pdf's AcroForm fields via `pdftk dump_data_fields_utf8`.
     *
     * @return array<string, array{type: string, onValue: ?string}> keyed by field name,
     *         in order of first appearance. "onValue" is the non-"Off" state for checkboxes.
     */
    public function inspectPdfFields(string $absolutePath): array
    {
        $command = sprintf('pdftk %s dump_data_fields_utf8 2>&1', escapeshellarg($absolutePath));
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Не удалось прочитать поля pdf-шаблона: '.implode("\n", $output));
        }

        $fields = [];
        $current = null;

        foreach ($output as $line) {
            if ($line === '---') {
                $this->commitPdfField($fields, $current);
                $current = ['stateOptions' => []];

                continue;
            }

            if ($current === null || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_pad(explode(': ', $line, 2), 2, '');
            if ($key === 'FieldStateOption') {
                $current['stateOptions'][] = $value;
            } else {
                $current[$key] = $value;
            }
        }
        $this->commitPdfField($fields, $current);

        return $fields;
    }

    private function commitPdfField(array &$fields, ?array $current): void
    {
        if ($current === null || ! isset($current['FieldName']) || $current['FieldName'] === '') {
            return;
        }

        $onValue = null;
        foreach ($current['stateOptions'] as $option) {
            if ($option !== 'Off') {
                $onValue = $option;

                break;
            }
        }

        $fields[$current['FieldName']] = [
            'type' => $current['FieldType'] ?? 'Text',
            'onValue' => $onValue,
        ];
    }

    private function looksLikeBooleanBlock(string $key): bool
    {
        return str_starts_with($key, 'has_')
            || str_starts_with($key, 'is_')
            || str_starts_with($key, 'include_')
            || str_starts_with($key, 'show_')
            || str_starts_with($key, 'need_')
            || str_starts_with($key, 'with_');
    }

    /**
     * @return string[]
     */
    private function extractKeysFromDocx(string $absolutePath): array
    {
        $processor = new TemplateProcessor($absolutePath);
        $processor->setMacroChars('{{', '}}');

        $keys = [];
        foreach ($processor->getVariables() as $variable) {
            $key = trim($variable);
            // Block-closing markers ({{/key}}) are not standalone variables.
            if ($key === '' || str_starts_with($key, '/') || in_array($key, $keys, true)) {
                continue;
            }
            $keys[] = $key;
        }

        return $keys;
    }

    private function readPlainText(string $absolutePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Не удалось открыть файл шаблона.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Файл шаблона повреждён или не является docx.');
        }

        return preg_replace('/<[^>]+>/', '', $xml);
    }
}
