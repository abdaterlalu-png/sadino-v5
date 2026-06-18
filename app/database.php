<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $driver = (string)env('DB_DRIVER', 'mysql');
    if ($driver === 'sqlite') {
        $path = (string)env('DB_DATABASE', dirname(__DIR__) . '/storage/sadino.sqlite');
        $pdo = new PDO('sqlite:' . $path);
    } else {
        $host = (string)env('DB_HOST', 'db');
        $port = (string)env('DB_PORT', '3306');
        $name = (string)env('DB_DATABASE', 'sadino');
        $user = (string)env('DB_USERNAME', 'sadino');
        $pass = (string)env('DB_PASSWORD', '');
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
