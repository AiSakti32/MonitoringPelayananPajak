<?php

declare(strict_types=1);

function view(string $name, array $data = [], ?string $layout = 'layouts/app'): void
{
    \App\Core\View::render($name, $data, $layout);
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    $titles = [
        403 => 'Akses ditolak',
        404 => 'Halaman tidak ditemukan',
        429 => 'Terlalu banyak percobaan',
        500 => 'Terjadi kesalahan',
    ];
    $title = $titles[$status] ?? 'Error';
    if ($message === '') {
        $message = $title;
    }

    if (\App\Core\Session::isLoggedIn()) {
        view('errors/error', [
            'pageTitle' => $title,
            'status' => $status,
            'message' => $message,
        ]);
    } else {
        view('errors/error', [
            'pageTitle' => $title,
            'status' => $status,
            'message' => $message,
        ], 'layouts/auth');
    }
    exit;
}
