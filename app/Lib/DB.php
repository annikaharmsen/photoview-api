<?php

declare(strict_types=1);

namespace App\Lib;

use PDO;

final class DB
{

    public static function query(PDO $pdo, string $sql, array $params = []) {

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    // get one row
    public static function one(PDO $pdo, string $sql, array $params = []): array|null
    {
        $stmt = self::query($pdo, $sql, $params);

        return $stmt->fetch() ?: null;
    }

    // get all rows
    public static function all(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = self::query($pdo, $sql, $params);

        return $stmt->fetchAll();
    }

    // INSERT/UPDATE/DELETE and return affected rows
    public static function run(PDO $pdo, string $sql, array $params = []): int
    {
        $stmt = self::query($pdo, $sql, $params);

        return $stmt->rowCount();
    }

    // insert a row and return its ID 
    public static function insert(PDO $pdo, string $sql, array $params = []): int
    {
        $stmt = self::query($pdo, $sql, $params);        
        
        return (int)$pdo->lastInsertId();
    }
}
