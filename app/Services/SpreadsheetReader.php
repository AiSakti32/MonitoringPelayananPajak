<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Lightweight reader for .xlsx (ZipArchive) and .csv — no Composer dependency.
 *
 * @return list<list<string>> rows including header
 */
final class SpreadsheetReader
{
    /**
     * @return list<list<string>>
     */
    public function read(string $path, string $originalName = ''): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File tidak ditemukan.');
        }

        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
        return match ($ext) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            'xls' => throw new RuntimeException('Format .xls lama tidak didukung. Simpan ulang sebagai .xlsx atau .csv.'),
            default => throw new RuntimeException('Format file tidak didukung. Gunakan .xlsx atau .csv.'),
        };
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Gagal membaca file CSV.');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $delimiter = $this->detectDelimiter($raw);
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('Gagal membuka buffer CSV.');
        }
        fwrite($fh, $raw);
        rewind($fh);

        $rows = [];
        while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            $rows[] = array_map(static fn ($v) => trim((string) $v), $data);
        }
        fclose($fh);

        return $this->trimTrailingEmptyRows($rows);
    }

    private function detectDelimiter(string $sample): string
    {
        $firstLine = strtok($sample, "\r\n") ?: '';
        $comma = substr_count($firstLine, ',');
        $semi = substr_count($firstLine, ';');
        $tab = substr_count($firstLine, "\t");
        if ($semi > $comma && $semi >= $tab) {
            return ';';
        }
        if ($tab > $comma && $tab >= $semi) {
            return "\t";
        }
        return ',';
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZipArchive PHP diperlukan untuk membaca .xlsx.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak valid atau rusak.');
        }

        try {
            $shared = $this->parseSharedStrings((string) ($zip->getFromName('xl/sharedStrings.xml') ?: ''));
            $sheetPath = $this->resolveFirstSheetPath($zip);
            $sheetXml = (string) ($zip->getFromName($sheetPath) ?: '');
            if ($sheetXml === '') {
                throw new RuntimeException('Worksheet Excel kosong / tidak ditemukan.');
            }
            return $this->parseSheet($sheetXml, $shared);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $shared = [];
        if (!preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $xml, $sis)) {
            return [];
        }
        foreach ($sis[1] as $si) {
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $tm)) {
                $parts = array_map(
                    static fn (string $t): string => html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $tm[1]
                );
                $shared[] = implode('', $parts);
            } else {
                $shared[] = '';
            }
        }
        return $shared;
    }

    private function resolveFirstSheetPath(\ZipArchive $zip): string
    {
        $wb = (string) ($zip->getFromName('xl/workbook.xml') ?: '');
        $rels = (string) ($zip->getFromName('xl/_rels/workbook.xml.rels') ?: '');
        if ($wb !== '' && $rels !== '' && preg_match('/<sheet[^>]*r:id="(rId\d+)"/i', $wb, $m)) {
            $rid = $m[1];
            if (preg_match('/Id="' . preg_quote($rid, '/') . '"[^>]*Target="([^"]+)"/i', $rels, $tm)) {
                $target = ltrim(str_replace('\\', '/', $tm[1]), '/');
                if (!str_starts_with($target, 'xl/')) {
                    $target = 'xl/' . $target;
                }
                return $target;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param list<string> $shared
     * @return list<list<string>>
     */
    private function parseSheet(string $sheetXml, array $shared): array
    {
        // Split by row for robustness with empty cells
        if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches, PREG_SET_ORDER)) {
            return [];
        }

        $grid = [];
        $maxCol = 0;
        foreach ($rowMatches as $rowMatch) {
            $rowXml = $rowMatch[0];
            $rowNum = 0;
            if (preg_match('/<row[^>]*\br="(\d+)"/', $rowXml, $rm)) {
                $rowNum = (int) $rm[1];
            }
            if ($rowNum < 1) {
                continue;
            }

            if (!preg_match_all('/<c\b([^>]*)>(.*?)<\/c>|<c\b([^>]*)\/>/s', $rowXml, $cells, PREG_SET_ORDER)) {
                $grid[$rowNum] = [];
                continue;
            }

            foreach ($cells as $cell) {
                $attrs = $cell[1] !== '' ? $cell[1] : ($cell[3] ?? '');
                $inner = $cell[2] ?? '';
                if (!preg_match('/\br="([A-Z]+)(\d+)"/', $attrs, $ref)) {
                    continue;
                }
                $colLetters = $ref[1];
                $colIndex = $this->columnIndex($colLetters);
                $maxCol = max($maxCol, $colIndex);

                $type = '';
                if (preg_match('/\bt="([^"]+)"/', $attrs, $tm)) {
                    $type = $tm[1];
                }

                $value = '';
                if ($type === 'inlineStr') {
                    if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $inner, $tm2)) {
                        $value = html_entity_decode(implode('', $tm2[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                    $raw = html_entity_decode($vm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if ($type === 's') {
                        $value = $shared[(int) $raw] ?? $raw;
                    } else {
                        $value = $raw;
                    }
                }

                $grid[$rowNum][$colIndex] = trim($value);
            }
        }

        ksort($grid);
        $rows = [];
        foreach ($grid as $cols) {
            $row = [];
            for ($i = 1; $i <= $maxCol; $i++) {
                $row[] = $cols[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $this->trimTrailingEmptyRows($rows);
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n;
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function trimTrailingEmptyRows(array $rows): array
    {
        while ($rows !== []) {
            $last = $rows[array_key_last($rows)];
            $allEmpty = true;
            foreach ($last as $cell) {
                if (trim((string) $cell) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if (!$allEmpty) {
                break;
            }
            array_pop($rows);
        }
        return array_values($rows);
    }
}
