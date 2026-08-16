<?php

declare(strict_types=1);

/**
 * Create the first admin user (CLI only).
 *
 * Usage:
 *   php scripts/create_admin.php --username=admin --name="Administrator" --password="ChangeMeNow!"
 *
 * Optional:
 *   --email=admin@example.com
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from CLI.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Repositories\UserRepository;

App::boot($basePath);

$opts = getopt('', ['username:', 'name:', 'password:', 'email::']);
$username = trim((string) ($opts['username'] ?? ''));
$name = trim((string) ($opts['name'] ?? ''));
$password = (string) ($opts['password'] ?? '');
$email = trim((string) ($opts['email'] ?? ''));

if ($username === '' || $name === '' || $password === '') {
    fwrite(STDERR, "Required: --username --name --password [--email]\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters. Change it after first login.\n");
    exit(1);
}

try {
    $repo = new UserRepository();
    $existing = $repo->findByUsername($username);
    if ($existing !== null) {
        fwrite(STDERR, "Username already exists.\n");
        exit(1);
    }

    $id = $repo->create([
        'username' => $username,
        'email' => $email !== '' ? $email : null,
        'password_hash' => hash_password($password),
        'full_name' => $name,
        'role' => 'admin',
        'officer_id' => null,
        'is_active' => 1,
    ]);

    fwrite(STDOUT, "Admin user created successfully. ID={$id}, username={$username}\n");
    fwrite(STDOUT, "IMPORTANT: Change the password after first login. Do not commit passwords.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
