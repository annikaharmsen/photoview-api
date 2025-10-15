<?php

require_once 'autoload.php';

// Determine which database to use based on environment
$useTestDb = $_ENV['USE_SQLITE'] ?? 'false';

if ($useTestDb === 'true') {
    // SQLite for local testing
    $dbPath = __DIR__ . '/../../database.sqlite';
    $dsn = "sqlite:$dbPath";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, null, null, $options);
        // Enable foreign keys for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
    } catch (\PDOException $e) {
        throw new \PDOException("SQLite connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
} else {
    // MySQL for production
    $host = $_ENV['DB_HOST'];
    $db   = $_ENV['DB_NAME'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException("MySQL connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
}