<?php
declare(strict_types=1);

namespace App\Core;

/** Journal technique (fichiers dans storage/logs, jamais servis par Apache). */
final class Logger
{
    public static function error(\Throwable|string $e, array $context = []): void
    {
        $msg = $e instanceof \Throwable
            ? sprintf("%s: %s in %s:%d\n%s", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString())
            : $e;
        self::write('ERROR', $msg, $context);
    }

    public static function info(string $msg, array $context = []): void
    {
        self::write('INFO', $msg, $context);
    }

    private static function write(string $level, string $msg, array $context): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s %s%s\n", date('Y-m-d H:i:s'), $level, $msg, $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '');
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
