<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Couleur principale d'un club, tirée de son logo (pixels colorés les plus fréquents),
 * enregistrée dans clubs.brand_color et réutilisée pour les cartes du sélecteur de club.
 */
final class ClubColorService
{
    public const FALLBACK = '#71717a';

    /** Couleur du club, calculée une seule fois à partir du logo si elle n'est pas encore connue. */
    public static function forClub(array $club): string
    {
        if (!empty($club['brand_color'])) {
            return $club['brand_color'];
        }
        $color = self::fromLogo($club['logo_path'] ?? null) ?? self::FALLBACK;
        if (!empty($club['id'])) {
            Database::run('UPDATE clubs SET brand_color = ? WHERE id = ? AND brand_color IS NULL', [$color, (int) $club['id']]);
        }
        return $color;
    }

    /** @param list<array> $clubs ajoute 'color' à chaque club */
    public static function decorate(array $clubs): array
    {
        foreach ($clubs as &$c) {
            $c['color'] = self::forClub($c);
        }
        return $clubs;
    }

    public static function fromLogo(?string $logoPath): ?string
    {
        $file = self::file($logoPath);
        if (!$file || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (!$img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $small = imagecreatetruecolor(40, 40);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 40, 40, $w, $h);
        $buckets = [];
        for ($x = 0; $x < 40; $x++) {
            for ($y = 0; $y < 40; $y++) {
                $rgba = imagecolorat($small, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                if ($a > 60) {
                    continue; // transparent
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $sat = $max ? ($max - $min) / $max : 0;
                if ($sat < .28 || $max < 45 || $max > 250 && $sat < .4) {
                    continue; // blanc, noir, gris : ne définissent pas la couleur d'un club
                }
                $key = intdiv($r, 32) . '-' . intdiv($g, 32) . '-' . intdiv($b, 32);
                $buckets[$key] ??= ['n' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                $buckets[$key]['n']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }
        if (!$buckets) {
            return null;
        }
        usort($buckets, fn ($x, $y) => $y['n'] <=> $x['n']);
        $top = $buckets[0];
        if ($top['n'] < 6) {
            return null;
        }
        return sprintf('#%02x%02x%02x', intdiv($top['r'], $top['n']), intdiv($top['g'], $top['n']), intdiv($top['b'], $top['n']));
    }

    private static function file(?string $logoPath): ?string
    {
        if (!$logoPath) {
            return null;
        }
        if (preg_match('#^/?media/(\d+)$#', $logoPath, $m)) {
            $d = Database::one('SELECT stored_name FROM documents WHERE id = ?', [(int) $m[1]]);
            return $d ? DocumentService::dir() . '/' . $d['stored_name'] : null;
        }
        $f = BASE_PATH . '/public/' . ltrim($logoPath, '/');
        return is_file($f) ? $f : null;
    }
}
