<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/functions.php';
load_env($root . '/.env');
require_once $root . '/app/database.php';

$tz = (string)env('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($tz);

if (PHP_SAPI !== 'cli') {
    apply_security_headers();
    session_name('SADINOSESSID');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => secure_cookie_enabled(),
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'save_path' => $root . '/storage/sessions',
    ]);
}
require_once $root . '/app/auth.php';
require_once $root . '/app/repository.php';
require_once $root . '/app/import_service.php';
require_once $root . '/app/design_service.php';
