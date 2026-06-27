<?php

namespace App\Services;

use App\Models\TemplateVersion;
use App\Models\Variable;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class DocumentGenerator
{
    public function __construct(private VariableExtractor $extractor)
    {
    }

    /**
     * Render a docx template version with the given values and store the result.
     *
     * @param Collection<int, Variable> $variables
     * @param array<string, mixed> $values keyed by variable key
     * @return string storage path (relative to the "public" disk) of the generated file
     */
    public function generate(TemplateVersion $version, Collection $variables, array $values): string
    {
        // Required by ТЗ 4.2 ("экранируются спецсимволы разметки"); off by default in PhpWord.
        Settings::setOutputEscapingEnabled(true);

        $processor = new TemplateProcessor($version->full_path);
        $processor->setMacroChars('{{', '}}');

        foreach ($variables as $variable) {
            $value = $values[$variable->key] ?? $variable->default_value;

            if ($variable->type === 'table') {
                $this->applyTableBlock($processor, $variable->key, is_array($value) ? $value : []);
                continue;
            }

            if ($variable->type === 'boolean' && $this->extractor->hasBlock($version->full_path, $variable->key)) {
                $this->applyConditionalBlock($processor, $variable->key, (bool) $value);
                continue;
            }

            $processor->setValue($variable->key, $this->formatScalar($variable, $value));
        }

        $relativePath = 'documents/'.Str::random(40).'.docx';
        $absolutePath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }
        $processor->saveAs($absolutePath);

        return $relativePath;
    }

    /**
     * Build a filename from a number- and date-typed variable when present,
     * falling back to the template name (ТЗ 4.4: "имя файла ... по номеру и дате").
     */
    public function buildFilename(string $templateName, Collection $variables, array $values, string $extension): string
    {
        $number = null;
        $date = null;

        foreach ($variables as $variable) {
            $value = $values[$variable->key] ?? null;
            if ($value === null) {
                continue;
            }
            if ($number === null && in_array($variable->type, ['number', 'text'], true) && str_contains($variable->key, 'number')) {
                $number = $value;
            }
            if ($date === null && $variable->type === 'date') {
                $date = $value;
            }
        }

        $slug = Str::slug($templateName) ?: 'document';
        $parts = array_filter([$slug, $number, $date ? Carbon::parse($date)->format('Y-m-d') : null]);

        return Str::slug(implode('-', $parts)).'.'.$extension;
    }

    /**
     * Convert a generated docx (relative to the "public" disk) to pdf via headless LibreOffice.
     * Result is cached next to the source file, so repeat downloads are free.
     *
     * @return string relative path (to the "public" disk) of the pdf file
     */
    public function convertToPdf(string $relativeDocxPath): string
    {
        $relativePdfPath = preg_replace('/\.docx$/', '.pdf', $relativeDocxPath);
        $absolutePdfPath = storage_path('app/public/'.$relativePdfPath);

        if (file_exists($absolutePdfPath)) {
            return $relativePdfPath;
        }

        $absoluteDocxPath = storage_path('app/public/'.$relativeDocxPath);
        $outDir = dirname($absoluteDocxPath);

        $command = sprintf(
            'soffice --headless --norestore --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($outDir),
            escapeshellarg($absoluteDocxPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($absolutePdfPath)) {
            throw new RuntimeException('Не удалось сконвертировать документ в pdf: '.implode("\n", $output));
        }

        return $relativePdfPath;
    }

    private function applyTableBlock(TemplateProcessor $processor, string $key, array $rows): void
    {
        if ($rows === []) {
            $processor->deleteBlock($key);

            return;
        }

        $processor->cloneBlock($key, count($rows), true, true);

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $column => $value) {
                $processor->setValue($column.'#'.($i + 1), (string) $value);
            }
        }
    }

    private function applyConditionalBlock(TemplateProcessor $processor, string $key, bool $shouldInclude): void
    {
        if ($shouldInclude) {
            $processor->cloneBlock($key, 1, true, false);
        } else {
            $processor->deleteBlock($key);
        }
    }

    private function formatScalar(Variable $variable, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($variable->type) {
            'boolean' => $value ? 'Да' : 'Нет',
            'date' => Carbon::parse($value)->format('d.m.Y'),
            'currency' => number_format((float) $value, 2, ',', ' ').' ₽',
            default => (string) $value,
        };
    }
}
