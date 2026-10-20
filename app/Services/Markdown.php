<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Markdown simplifié et sûr pour les textes éditables : paragraphes, retours à la ligne, **gras**, *italique*,
 * titres (## / ###), listes (- ), liens [texte](https://…). Tout le HTML saisi est échappé.
 */
final class Markdown
{
    public static function render(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $text));
        if ($text === '') {
            return '';
        }
        $html = '';
        foreach (preg_split('/\n{2,}/', $text) as $block) {
            $lines = explode("\n", $block);
            if (preg_match('/^#{2,4}\s/', $lines[0]) && count($lines) === 1) {
                $level = min(4, strspn($lines[0], '#') + 1);
                $html .= "<h$level>" . self::inline(trim(substr($lines[0], $level - 1))) . "</h$level>";
                continue;
            }
            if (count(array_filter($lines, fn ($l) => preg_match('/^\s*[-•*]\s+/', $l))) === count($lines)) {
                $html .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . self::inline(preg_replace('/^\s*[-•*]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
                continue;
            }
            $html .= '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
        }
        return $html;
    }

    public static function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
            $href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('#^(https?://|mailto:|tel:|/)#i', $href)) {
                return $m[1];
            }
            $external = preg_match('#^https?://#i', $href);
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/u', '<em>$1</em>', $s);
        $s = preg_replace('/\^(\w+)\^/u', '<sup>$1</sup>', $s);
        return $s;
    }

    public static function plain(?string $text, int $max = 200): string
    {
        $t = trim(preg_replace('/[*#\[\]]|\(https?:[^)]+\)/', '', (string) $text));
        $t = preg_replace('/\s+/', ' ', $t);
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }
}
