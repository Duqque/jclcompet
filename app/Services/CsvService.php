<?php
declare(strict_types=1);

namespace App\Services;

final class CsvService
{
    /** CSV compatible Excel (UTF-8 avec BOM, séparateur point-virgule). Protège contre l'injection de formules. */
    public static function build(array $headers, array $rows): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, array_map([self::class, 'safe'], $row), ';', '"', '');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return (string) $csv;
    }

    private static function safe(mixed $v): string
    {
        $s = (string) ($v ?? '');
        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s) ? "'" . $s : $s;
    }

    /** @return array<int, array<string, string>> lignes indexées par nom de colonne normalisé */
    public static function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        if (!$lines || $lines[0] === '') {
            return [];
        }
        $sep = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(fn ($h) => self::normalizeHeader($h), str_getcsv($lines[0], $sep, '"', ''));
        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $sep, '"', '');
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = trim((string) ($values[$i] ?? ''));
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_', '-' => '_']);
        return match ($h) {
            'prenom', 'firstname', 'first_name' => 'prenom',
            'nom', 'lastname', 'last_name' => 'nom',
            'sexe', 'sex', 'genre' => 'sexe',
            'date_de_naissance', 'naissance', 'date_naissance', 'birth_date' => 'date_naissance',
            'poids', 'poids_declare', 'poids_(kg)', 'weight' => 'poids_declare',
            'numero_de_licence', 'licence', 'license' => 'licence',
            default => $h,
        };
    }
}
