<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Migrator
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? BASE_PATH . '/database/migrations';
    }

    private function ensureTable(): void
    {
        Database::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function files(): array
    {
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    public function status(): array
    {
        $this->ensureTable();
        $done = array_flip(Database::column('SELECT version FROM schema_migrations'));
        $out = [];
        foreach ($this->files() as $f) {
            $out[basename($f)] = isset($done[basename($f)]);
        }
        return $out;
    }

    /** @return string[] migrations appliquées */
    public function migrate(): array
    {
        $applied = [];
        foreach ($this->status() as $file => $isApplied) {
            if ($isApplied) {
                continue;
            }
            $sql = (string) file_get_contents($this->dir . '/' . $file);
            foreach (self::splitStatements($sql) as $statement) {
                Database::pdo()->exec($statement);
            }
            Database::run('INSERT INTO schema_migrations (version) VALUES (?)', [$file]);
            $applied[] = $file;
        }
        return $applied;
    }

    /** Découpe un script SQL en instructions (gère les chaînes et les commentaires « -- »). */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $quote = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if (!$inString && $c === '-' && ($sql[$i + 1] ?? '') === '-') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
                $buffer .= "\n";
                continue;
            }
            if ($inString) {
                $buffer .= $c;
                if ($c === '\\') {
                    $buffer .= $sql[++$i] ?? '';
                    continue;
                }
                if ($c === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $inString = true;
                $quote = $c;
                $buffer .= $c;
                continue;
            }
            if ($c === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $c;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
