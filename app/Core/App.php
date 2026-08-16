<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    private static array $config = [];
    private static bool $booted = false;

    public static function boot(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        require_once $basePath . '/app/Helpers/env.php';
        load_env($basePath . '/.env');

        require_once $basePath . '/app/Helpers/security.php';
        require_once $basePath . '/app/Helpers/date.php';
        require_once $basePath . '/app/Helpers/response.php';
        require_once $basePath . '/app/Helpers/ui.php';
        require_once $basePath . '/app/Helpers/alerts.php';

        self::$config['app'] = require $basePath . '/config/app.php';
        self::$config['database'] = require $basePath . '/config/database.php';

        $localFile = $basePath . '/config/local.php';
        if (is_file($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                self::$config = array_replace_recursive(self::$config, $local);
            }
        }

        date_default_timezone_set((string) (self::$config['app']['timezone'] ?? 'Asia/Jakarta'));

        $debug = (bool) (self::$config['app']['debug'] ?? false);
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', $basePath . '/storage/logs/php-error.log');
        }

        Session::start(self::$config['app']['session'] ?? []);
        self::$booted = true;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
