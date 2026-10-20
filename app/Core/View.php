<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gabarits PHP natifs. Toute donnée affichée doit passer par e() (échappement HTML).
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    public static function capture(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Gabarit introuvable : $template");
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }
}
