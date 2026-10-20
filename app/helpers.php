<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) Config::get('app.base_path', ''), '/');
    $u = $base . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u === '' ? '/' : $u;
}

function absolute_url(string $path = '/', array $query = []): string
{
    return rtrim((string) Config::get('app.url'), '/') . '/' . ltrim(url($path, $query), '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function json_attr(mixed $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}

function json_script(string $id, mixed $data): string
{
    return '<script type="application/json" id="' . e($id) . '">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . '</script>';
}

const FR_MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const FR_DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

function fr_date(?string $date, bool $withDay = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    $s = (int) date('j', $ts) . (date('j', $ts) === '1' ? 'er' : '') . ' ' . FR_MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withDay ? FR_DAYS[(int) date('w', $ts)] . ' ' . $s : $s;
}

function fr_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return fr_date($dt) . ' à ' . date('H\hi', strtotime($dt));
}

function short_datetime(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

function fr_time(?string $t): string
{
    return $t ? date('H\hi', strtotime($t)) : '—';
}

function money(int|float|null $cents): string
{
    return number_format(((int) $cents) / 100, 2, ',', "\u{202F}") . "\u{00A0}€";
}

function kg(mixed $v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . ' kg';
}

function sex_label(?string $sex, bool $plural = false): string
{
    return match ($sex) {
        'M' => $plural ? 'Masculins' : 'Masculin',
        'F' => $plural ? 'Féminines' : 'Féminin',
        default => '—',
    };
}

function duration_label(int $seconds): string
{
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad((string) $s, 2, '0', STR_PAD_LEFT);
}

function hours_label(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return $h > 0 ? sprintf('%d h %02d', $h, $m) : sprintf('%d min', $m);
}

function initials(string $name): string
{
    $words = preg_split('/[\s\-]+/u', trim($name)) ?: [];
    $skip = ['de', 'du', 'des', 'la', 'le', 'les', 'et', "d'", 'l\''];
    $letters = '';
    foreach ($words as $w) {
        if ($w === '' || in_array(mb_strtolower($w), $skip, true)) {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($w, 0, 1));
        if (mb_strlen($letters) >= 3) {
            break;
        }
    }
    return $letters ?: '?';
}

function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['__old'][$key] ?? $default;
}

function field_error(string $key): string
{
    $err = $GLOBALS['__errors'][$key] ?? null;
    return $err ? '<p class="field-error" role="alert">' . e($err) . '</p>' : '';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $v): string
{
    return $v ? ' checked' : '';
}

function slugify(string $text): string
{
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'competition';
}

/** Icônes en ligne (traits simples, couleur héritée). */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18.5 14.8c1.8.7 2.8 2.4 3 5.2"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'timer' => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 1.8"/><path d="M9.5 2.5h5"/><path d="M12 2.5v3"/>',
        'trophy' => '<path d="M7.5 4h9v5a4.5 4.5 0 0 1-9 0z"/><path d="M7.5 6H4.5a3 3 0 0 0 3 4"/><path d="M16.5 6h3a3 3 0 0 1-3 4"/><path d="M12 13.5V17"/><path d="M8.5 20.5h7l-.8-3.5h-5.4z"/>',
        'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M21 20H3"/>',
        'sliders' => '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h3"/><path d="M11 12h9"/><circle cx="9" cy="12" r="2"/><path d="M4 18h12"/><path d="M20 18h0"/><circle cx="18" cy="18" r="2"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.5-1.5 1.7-2.3 3.2-2.3s2.7.8 3.2 2.3"/><path d="M14.5 10h4"/><path d="M14.5 13.5h3"/>',
        'bag' => '<path d="M5 8h14l-1 12H6z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="9.5" r="1.8"/><path d="m4 18 5-5 3.5 3.5L16 13l4 4"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.5 2.6 3.5 5.4 3.5 8.5s-1 5.9-3.5 8.5c-2.5-2.6-3.5-5.4-3.5-8.5s1-5.9 3.5-8.5z"/>',
        'list' => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><circle cx="4.5" cy="6" r=".6"/><circle cx="4.5" cy="12" r=".6"/><circle cx="4.5" cy="18" r=".6"/>',
        'tv' => '<rect x="3" y="5" width="18" height="12" rx="2.5"/><path d="M8.5 21h7"/><path d="M12 17v4"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'x' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.3 4.9L20 16"/><path d="M20 20v-4h-4"/>',
        'expand' => '<path d="M4 9V4h5"/><path d="M20 9V4h-5"/><path d="M4 15v5h5"/><path d="M20 15v5h-5"/>',
        'shrink' => '<path d="M9 4v5H4"/><path d="M15 4v5h5"/><path d="M9 20v-5H4"/><path d="M15 20v-5h5"/>',
        'flag' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'arrow' => '<path d="M7 17 17 7"/><path d="M8 7h9v9"/>',
        'download' => '<path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M5 20h14"/>',
        'upload' => '<path d="M12 20V9"/><path d="m7.5 13.5 4.5-4.5 4.5 4.5"/><path d="M5 4h14"/>',
        'logout' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h10"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 6.1A9.6 9.6 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-3.2 3.9"/><path d="M6.4 7.4A16 16 0 0 0 2.5 12S6 18 12 18a9 9 0 0 0 4.3-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'whistle' => '<circle cx="9" cy="14" r="5"/><path d="M13 10.5 21 6v4l-6 2.3"/><path d="M9 14h.01"/>',
        'clipboard' => '<rect x="5" y="4.5" width="14" height="16" rx="2.5"/><path d="M9 4.5V3.5h6v1"/><path d="m8.5 12.5 2.2 2.2 4.8-4.8"/>',
        'shield' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8 7.5 9.5 4.3-1.5 7.5-4.9 7.5-9.5V6z"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'camera' => '<path d="M4 8.5h3l1.5-2.5h7L17 8.5h3a1 1 0 0 1 1 1V18a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V9.5a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'home' => '<path d="M4 11 12 4l8 7"/><path d="M6 9.5V20h12V9.5"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/** Avatar : photo du judoka (document privé) ou initiales. */
function avatar(?int $photoId, string $name, string $class = 'avatar'): string
{
    if ($photoId) {
        return '<img class="' . e($class) . '" src="' . e(url('/media/' . $photoId, ['mini' => 1])) . '" alt="" loading="lazy">';
    }
    return '<span class="avatar-mono ' . e($class) . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

/** Image embarquée dans un PDF (data URI) à partir d'un chemin « media/ID » ou d'un fichier public. */
function pdf_image(?string $path): ?string
{
    static $cache = [];
    if (!$path) {
        return null;
    }
    if (array_key_exists($path, $cache)) {
        return $cache[$path];
    }
    $file = null;
    $mime = null;
    if (preg_match('#^/?media/(\d+)$#', $path, $m)) {
        $d = \App\Core\Database::one('SELECT stored_name, thumb_name, mime_type FROM documents WHERE id = ?', [(int) $m[1]]);
        if ($d) {
            $dir = \App\Services\DocumentService::dir();
            $file = $d['thumb_name'] && is_file($dir . '/' . $d['thumb_name']) ? $dir . '/' . $d['thumb_name'] : $dir . '/' . $d['stored_name'];
            $mime = $d['mime_type'];
        }
    } elseif (is_file(BASE_PATH . '/public/' . ltrim($path, '/'))) {
        $file = BASE_PATH . '/public/' . ltrim($path, '/');
        $mime = mime_content_type($file) ?: 'image/png';
    }
    if (!$file || !is_file($file) || !in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        return $cache[$path] = null;
    }
    if ($mime === 'image/webp') {
        return $cache[$path] = null; // non pris en charge par le moteur PDF
    }
    return $cache[$path] = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
}

/** Dessin de ceinture : rouge (premier appelé) ou blanche (second appelé). $asImg = data URI pour les PDF. */
function belt(string $color, bool $asImg = false): string
{
    [$fill, $stroke] = $color === 'red' ? ['#c21a2c', '#8f1220'] : ['#ffffff', '#a1a1aa'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 22" width="34" height="22">'
        . '<g fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.2" stroke-linejoin="round">'
        . '<path d="M14.5 11.5 9 20.5h4.6l3.4-6.6z"/><path d="M19.5 11.5 25 20.5h-4.6L17 13.9z"/>'
        . '<rect x="1.5" y="5" width="31" height="6" rx="2"/><rect x="12.5" y="3.2" width="9" height="9.4" rx="2.6"/></g></svg>';
    if ($asImg) {
        return '<img class="belt" src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="" width="17" height="11">';
    }
    return '<span class="belt belt-' . ($color === 'red' ? 'red' : 'white') . '" aria-hidden="true">' . $svg . '</span>';
}

/**
 * Logo de club pour les PDF : toujours dans une carte blanche, proportions respectées (jamais déformé).
 * $box = côté de la carte en mm ; à défaut de logo, initiales du club.
 */
function pdf_logo(?string $path, string $name, float $box = 6.5): string
{
    $src = pdf_image($path);
    $inner = $box * .8;
    if ($src) {
        $raw = base64_decode(substr($src, strpos($src, ',') + 1));
        $size = @getimagesizefromstring($raw) ?: [1, 1];
        $ratio = $size[1] > 0 ? $size[0] / $size[1] : 1;
        [$w, $h] = $ratio >= 1 ? [$inner, $inner / $ratio] : [$inner * $ratio, $inner];
        // La carte blanche est l'image elle-même (fond, bordure, marges intérieures) : alignement parfait avec le texte.
        $pv = ($box - $h) / 2;
        $ph = ($box - $w) / 2;
        return '<img class="lbox-img" src="' . e($src) . '" alt="" style="width:' . round($w, 2) . 'mm;height:' . round($h, 2) . 'mm;padding:' . round($pv, 2) . 'mm ' . round($ph, 2) . 'mm">';
    }
    return '<span class="lbox mono" style="width:' . round($box, 2) . 'mm;height:' . round($box, 2) . 'mm;line-height:' . round($box, 2) . 'mm;font-size:' . round($box * .75, 1) . 'pt">' . e(initials($name)) . '</span>';
}
