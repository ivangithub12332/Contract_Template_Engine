<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class VariableExtractor
{
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
