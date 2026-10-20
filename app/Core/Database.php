<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Accès PDO unique. Toutes les requêtes passent par des requêtes préparées.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('database');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $tz = (new \DateTime('now', new \DateTimeZone(Config::get('app.timezone', 'Europe/Paris'))))->format('P');
            self::$pdo->exec("SET time_zone = '{$tz}', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        }
        return self::$pdo;
    }

    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $cols)),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn ($c) => "`$c` = :set_$c", array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) {
            $params['set_' . $k] = $v;
        }
        return self::run("UPDATE `$table` SET $set WHERE $where", $params + $whereParams)->rowCount();
    }

    /**
     * Exécute $fn dans une transaction. Rejoue jusqu'à 3 fois en cas de verrou mortel (1213) ou d'attente (1205).
     * Les transactions imbriquées réutilisent la transaction en cours.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $attempt = 0;
        while (true) {
            $attempt++;
            $pdo->beginTransaction();
            try {
                $result = $fn();
                $pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $code = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
                if (in_array($code, [1213, 1205], true) && $attempt < 3) {
                    usleep(50000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }

    /** Construit "IN (:p0, :p1…)" et remplit $params. */
    public static function in(array $values, array &$params, string $prefix = 'in'): string
    {
        if ($values === []) {
            return '(NULL)';
        }
        $names = [];
        foreach (array_values($values) as $i => $v) {
            $params[$prefix . $i] = $v;
            $names[] = ':' . $prefix . $i;
        }
        return '(' . implode(', ', $names) . ')';
    }
}
