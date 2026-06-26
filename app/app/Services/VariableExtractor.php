<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class VariableExtractor
{
    /**
     * Extract unique placeholder keys ({{key}}) from a template file, in order of first appearance.
     *
     * @return string[]
     */
    public function extractKeys(string $absolutePath, string $format): array
    {
        if ($format === 'docx') {
            return $this->extractKeysFromDocx($absolutePath);
        }

        throw new RuntimeException('Разметка переменных для pdf-шаблонов пока не поддерживается.');
    }

    /**
     * Check the raw markup for structural problems: unmatched braces, empty placeholders.
     *
     * @return string[] human-readable validation errors, empty when the markup is valid
     */
    public function validate(string $absolutePath, string $format): array
    {
        if ($format !== 'docx') {
            return ['Проверка разметки для pdf-шаблонов пока не поддерживается.'];
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
     * @return string[]
     */
    private function extractKeysFromDocx(string $absolutePath): array
    {
        $processor = new TemplateProcessor($absolutePath);
        $processor->setMacroChars('{{', '}}');

        $keys = [];
        foreach ($processor->getVariables() as $variable) {
            $key = trim($variable);
            if ($key !== '' && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
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
