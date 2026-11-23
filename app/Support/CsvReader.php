<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reads an uploaded CSV file that must follow one of our import templates.
 * Shared by the inventory item import and the POS sales import.
 *
 *  - The first row must be exactly the template's headers (case and spaces ignored).
 *  - The invisible BOM that Excel adds to "CSV UTF-8" files is removed.
 *  - Completely blank lines are skipped.
 *  - Row numbers match what users see in Excel (header = row 1).
 */
class CsvReader
{
    /**
     * @param  array<int, string>  $headers
     * @return array{rows: array<int, array<string, string>>, raw: array<int, array<string, string>>}|array{error: string}
     *         rows = trimmed values; raw = the values exactly as they were in the file.
     */
    public static function read(string $path, array $headers, int $maxRows): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['error' => 'The file could not be read.'];
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if (! $header) {
                return ['error' => 'The file is empty.'];
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $header);

            if ($header !== $headers) {
                return ['error' => 'The column headers do not match the template. Expected: '.implode(', ', $headers).'.'];
            }

            $rows = [];
            $raw = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                $rowNumber++;

                if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                if (count($rows) >= $maxRows) {
                    return ['error' => 'The file has more than '.number_format($maxRows).' rows. Split it into smaller files.'];
                }

                $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
                $raw[$rowNumber] = array_combine($headers, array_map(fn ($v) => (string) $v, $values));
                $rows[$rowNumber] = array_map(fn ($v) => trim($v), $raw[$rowNumber]);
            }

            if (! $rows) {
                return ['error' => 'The file has no data rows below the header.'];
            }

            return ['rows' => $rows, 'raw' => $raw];
        } finally {
            fclose($handle);
        }
    }
}
