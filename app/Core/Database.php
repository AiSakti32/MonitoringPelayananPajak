<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) App::config('database.host', 'localhost');
        $port = (string) App::config('database.port', '3306');
        $dbname = (string) App::config('database.database', '');
        $user = (string) App::config('database.username', '');
        $pass = (string) App::config('database.password', '');
        $charset = (string) App::config('database.charset', 'utf8mb4');
        $options = App::config('database.options', []);

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, is_array($options) ? $options : []);
        } catch (PDOException $e) {
            $debug = (bool) App::config('app.debug', false);
            $message = $debug
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Tidak dapat terhubung ke database. Hubungi administrator.';
            throw new RuntimeException($message, (int) $e->getCode(), $e);
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
