<?php

declare(strict_types=1);

/** Smoke CORE pages as admin. Requires php -S localhost:8080 -t public */

$base = 'http://localhost:8080';
$cookie = tempnam(sys_get_temp_dir(), 'klcore');

function http_req(string $method, string $url, string $cookie, array $post = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ];
    if ($post !== []) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = explode("\r\n\r\n", $raw, 2);
    return ['code' => $code, 'body' => $parts[1] ?? ''];
}

$login = http_req('GET', $base . '/login', $cookie);
if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $login['body'], $m)) {
    fwrite(STDERR, "CSRF missing\n");
    exit(1);
}
http_req('POST', $base . '/login', $cookie, [
    '_csrf' => $m[1],
    'username' => 'admin',
    'password' => 'Admin123!',
]);

$paths = [
    '/dashboard',
    '/cases',
    '/cases/create',
    '/monitoring/deadlines',
    '/monitoring/officers',
    '/alerts',
    '/master/officers',
    '/users',
    '/audit-logs',
    '/profile',
];

$fail = 0;
foreach ($paths as $path) {
    $r = http_req('GET', $base . $path, $cookie);
    $bad = str_contains(strtolower($r['body']), 'akan diimplementasikan')
        || str_contains(strtolower($r['body']), 'coming soon')
        || str_contains(strtolower($r['body']), 'phase berikutnya');
    $ok = $r['code'] === 200 && !$bad;
    echo ($ok ? 'OK  ' : 'FAIL') . " {$path} HTTP {$r['code']}" . ($bad ? ' PLACEHOLDER' : '') . "\n";
    if (!$ok) {
        $fail++;
    }
}

// Detail of known case if exists
$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';
\App\Core\App::boot($basePath);
$row = \App\Core\Database::connection()->query("SELECT id FROM cases WHERE case_number='P1234567890' LIMIT 1")->fetch();
if ($row) {
    $r = http_req('GET', $base . '/cases/' . $row['id'], $cookie);
    $ok = $r['code'] === 200 && str_contains($r['body'], 'P1234567890');
    echo ($ok ? 'OK  ' : 'FAIL') . " /cases/{id} detail\n";
    if (!$ok) {
        $fail++;
    }
}

if ($fail > 0) {
    fwrite(STDERR, "SMOKE FAILED ({$fail})\n");
    exit(1);
}
echo "ALL CORE HTTP SMOKE PASSED\n";
