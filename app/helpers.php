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
