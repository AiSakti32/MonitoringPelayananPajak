<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;

/**
 * Simple file-based login rate limit per IP + username.
 */
final class RateLimitMiddleware
{
    public function handle(): void
    {
        // Applied explicitly inside AuthService for login attempts.
    }

    public static function tooManyAttempts(string $username): bool
    {
        $max = (int) App::config('app.login.max_attempts', 5);
        $decay = (int) App::config('app.login.decay_seconds', 300);
        $key = self::key($username);
        $data = self::read($key);

        if ($data === null) {
            return false;
        }

        if (time() - $data['started_at'] > $decay) {
            self::clear($username);
            return false;
        }

        return $data['attempts'] >= $max;
    }

    public static function hit(string $username): void
    {
        $key = self::key($username);
        $data = self::read($key);
        if ($data === null || (time() - $data['started_at']) > (int) App::config('app.login.decay_seconds', 300)) {
            $data = ['attempts' => 0, 'started_at' => time()];
        }
        $data['attempts']++;
        self::write($key, $data);
    }

    public static function clear(string $username): void
    {
        $file = self::file(self::key($username));
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function remainingSeconds(string $username): int
    {
        $decay = (int) App::config('app.login.decay_seconds', 300);
        $data = self::read(self::key($username));
        if ($data === null) {
            return 0;
        }
        $elapsed = time() - $data['started_at'];
        return max(0, $decay - $elapsed);
    }

    private static function key(string $username): string
    {
        return hash('sha256', Request::ip() . '|' . strtolower(trim($username)));
    }

    private static function file(string $key): string
    {
        $dir = base_path('storage/logs/rate_limit');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $key . '.json';
    }

    private static function read(string $key): ?array
    {
        $file = self::file($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function write(string $key, array $data): void
    {
        file_put_contents(self::file($key), json_encode($data), LOCK_EX);
    }
}
