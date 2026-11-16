<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Image haute définition d'un arbre de tableau (pour le PDF) : même mise en page que l'écran (BracketService::layout),
 * police Inter, noms sur les lignes, vainqueur sur la ligne du tour suivant, score dessous (vainqueur\perdant).
 */
final class BracketImage
{
    /** @return string PNG */
    public static function png(array $tree, float $scale = 3.0): string
    {
        $W = 190 * $scale;
        $H = max(34, min(46, 900 / max(1, $tree['rows']))) * $scale;
        $L = $H * .56;
        $w = (int) ceil(($tree['cols'] + .4) * $W);
        $h = (int) ceil(($tree['rows'] + .4) * $H);
        $im = imagecreatetruecolor($w, $h);
        imageantialias($im, true);
        $white = imagecolorallocate($im, 255, 255, 255);
        $ink = imagecolorallocate($im, 24, 24, 27);
        $muted = imagecolorallocate($im, 113, 113, 122);
        $line = imagecolorallocate($im, 161, 161, 170);
        $faint = imagecolorallocate($im, 212, 212, 216);
        $red = imagecolorallocate($im, 194, 26, 44);
        $gold = imagecolorallocate($im, 212, 167, 44);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);
        $font = BASE_PATH . '/public/assets/fonts/pdf/inter-';
        $f400 = $font . '400.ttf';
        $f700 = $font . '700.ttf';
        $size = 11 * $scale * .75; // points GD
        $small = 9 * $scale * .75;
        $thick = fn (int $t) => imagesetthickness($im, $t);
        $pad = 6 * $scale;
        $top = .2 * $H;

        foreach ($tree['joins'] as $j) {
            $thick((int) round(1.6 * $scale));
            $x = (int) round($j['col'] * $W);
            imageline($im, $x, (int) round($top + $j['y1'] * $H + $L), $x, (int) round($top + $j['y2'] * $H + $L), $ink);
        }
        $finalCol = $tree['cols'] - 1;
        foreach ($tree['items'] as $it) {
            $x1 = (int) round($it['col'] * $W);
            $isRoot = !$it['leaf'] && $it['col'] === $finalCol;
            $x2 = (int) round(($it['col'] + $it['span']) * $W + ($isRoot ? $W * .35 : 0));
            $y = (int) round($top + $it['y'] * $H + $L);
            $bye = $it['leaf'] && $it['type'] === 'bye';
            $live = !$it['leaf'] && in_array($it['status'] ?? null, ['in_progress', 'finished'], true);
            $color = $isRoot ? $gold : ($live ? $red : ($it['leaf'] ? $line : $ink));
            $thick((int) round(($isRoot ? 3 : ($it['leaf'] ? 1.2 : 1.6)) * $scale));
            if ($bye) {
                imagedashedline($im, $x1, $y, $x2, $y, $faint);
            } else {
                imageline($im, $x1, $y, $x2, $y, $color);
            }
            $a = $it['leaf'] ? ($it['athlete'] ?? null) : ($it['winner'] ?? null);
            $tx = $x1 + (int) $pad;
            $ty = $y - (int) (4 * $scale);
            $maxW = $x2 - $x1 - 2 * $pad;
            if ($a) {
                $last = $a['last_name'];
                $box = imagettfbbox($size, 0, $f700, $last . ' ');
                $lw = $box[2] - $box[0];
                imagettftext($im, $size, 0, $tx, $ty, $ink, $f700, self::fit($last, $f700, $size, $maxW));
                if ($lw < $maxW) {
                    $rest = $a['first_name'] . ($it['leaf'] && !empty($it['seed']) ? '' : '');
                    imagettftext($im, $size, 0, $tx + $lw, $ty, $ink, $f400, self::fit($rest, $f400, $size, $maxW - $lw));
                }
                if ($it['leaf'] && !empty($it['seed'])) {
                    $sb = imagettfbbox($small, 0, $f700, 'TS' . $it['seed']);
                    imagettftext($im, $small, 0, $x2 - (int) $pad - ($sb[2] - $sb[0]), $ty, $red, $f700, 'TS' . $it['seed']);
                }
            } elseif ($it['leaf'] && $it['type'] === 'pending') {
                imagettftext($im, $small, 0, $tx, $ty, $muted, $f400, self::fit((string) $it['text'], $f400, $small, $maxW));
            }
            $sy = $y + (int) (13 * $scale);
            if ($it['leaf'] && $a) {
                imagettftext($im, $small, 0, $tx, $sy, $muted, $f400, self::fit((string) ($a['club_short'] ?? ''), $f400, $small, $maxW));
            } elseif (!$it['leaf'] && !empty($it['score'])) {
                imagettftext($im, $small, 0, $tx, $sy, $ink, $f700, $it['score']);
            } elseif ($live) {
                imagettftext($im, $small, 0, $tx, $sy, $red, $f700, 'En cours · tapis ' . (int) $it['mat']);
            }
        }
        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);
        return (string) ob_get_clean();
    }

    /** Texte raccourci (…) s'il dépasse la largeur disponible. */
    private static function fit(string $text, string $font, float $size, float $max): string
    {
        $w = fn (string $t) => ($b = imagettfbbox($size, 0, $font, $t)) ? $b[2] - $b[0] : 0;
        if ($max <= 0 || $w($text) <= $max) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $w($text . '…') > $max) {
            $text = mb_substr($text, 0, -1);
        }
        return $text . '…';
    }

    public static function dataUri(array $tree): string
    {
        return 'data:image/png;base64,' . base64_encode(self::png($tree));
    }
}
