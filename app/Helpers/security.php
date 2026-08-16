<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return \App\Core\Session::csrfToken();
}

function csrf_field(): string
{
    $token = e(csrf_token());
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function base_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);
    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
}

function config(string $key, mixed $default = null): mixed
{
    return \App\Core\App::config($key, $default);
}

function app_base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base === '/' || $base === '\\' || $base === '.') {
        return '';
    }
    return $base;
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $url = app_base_path() . '/assets/' . $relative;
    $file = public_path('assets/' . $relative);
    if (is_file($file)) {
        $url .= '?v=' . (string) filemtime($file);
    }
    return $url;
}

function url(string $path = ''): string
{
    $base = app_base_path();
    if ($path === '' || $path === '/') {
        return ($base === '' ? '' : $base) . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . url($path), true, $status);
    exit;
}

function old(string $key, mixed $default = ''): mixed
{
    $old = \App\Core\Session::getFlash('old', []);
    return $old[$key] ?? $default;
}

function flash(string $key, mixed $value): void
{
    \App\Core\Session::flash($key, $value);
}

function get_flash(string $key, mixed $default = null): mixed
{
    return \App\Core\Session::getFlash($key, $default);
}

function is_admin(): bool
{
    $user = \App\Core\Session::user();
    return ($user['role'] ?? null) === 'admin';
}

function is_petugas(): bool
{
    $user = \App\Core\Session::user();
    return ($user['role'] ?? null) === 'petugas';
}

function auth_user(): ?array
{
    return \App\Core\Session::user();
}

function current_path(): string
{
    return \App\Core\Request::path();
}

function nav_active(string $prefix): string
{
    $path = current_path();
    if ($prefix === '/dashboard') {
        return $path === '/dashboard' || $path === '/' ? 'active' : '';
    }
    return str_starts_with($path, $prefix) ? 'active' : '';
}
