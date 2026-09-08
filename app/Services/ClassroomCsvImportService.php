<?php

declare(strict_types=1);

class ClassroomCsvImportService
{
    public function readCsvWithHeaders(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('No es pot llegir el CSV.');
        }

        $contents = file_get_contents($path, false, null, 0, 4096);
        $delimiter = $this->detectDelimiter((string) $contents);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No es pot obrir el CSV.');
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false || $headers === [null]) {
            fclose($handle);
            throw new RuntimeException('El CSV està buit.');
        }

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        }

        $headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);
        $rows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $row = array_pad($row, count($headers), '');
            $data = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $data[$header] = trim((string) ($row[$index] ?? ''));
                }
            }
            $rows[$lineNumber] = $data;
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    public function validateHeaders(array $headers, array $required, string $sheetName): void
    {
        foreach ($required as $header) {
            if (!in_array($header, $headers, true)) {
                throw new RuntimeException('Falta la columna ' . $header . ' a ' . $sheetName . '.');
            }
        }
    }

    public function parseActiveFlag(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || in_array($value, ['1', 'true', 'yes', 'si', 'sí', 'actiu', 'active'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'inactiu', 'inactive', 'arxivat'], true)) {
            return false;
        }

        throw new RuntimeException('is_active no vàlid: ' . $value);
    }

    public function normalizeText(string $text): string
    {
        $text = trim($text);

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private function detectDelimiter(string $sample): string
    {
        $counts = [
            "\t" => substr_count($sample, "\t"),
            ';' => substr_count($sample, ';'),
            ',' => substr_count($sample, ','),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim($header));
    }
}
