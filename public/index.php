<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Request;
use App\Core\Router;

try {
    App::boot($basePath);

    $router = new Router();
    require $basePath . '/routes/web.php';
    $router->dispatch(Request::method(), Request::path());
} catch (Throwable $e) {
    $debug = false;
    try {
        $debug = (bool) \App\Core\App::config('app.debug', false);
    } catch (Throwable) {
        $debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
    }

    http_response_code(500);
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Application Error: ' . $e->getMessage() . "\n\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo $e->getTraceAsString();
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Error</title></head><body>';
        echo '<h1>Terjadi kesalahan</h1><p>Silakan coba lagi atau hubungi administrator.</p>';
        echo '</body></html>';
        $log = $basePath . '/storage/logs/app-error.log';
        @file_put_contents($log, '[' . date('c') . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    }
}
