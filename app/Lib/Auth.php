<?php

declare(strict_types=1);

namespace App\Lib;

use PDO;

require_once "DB.php";

final class Auth
{
    public static function requireLogin(PDO $pdo): int
    {
        session_start();

        $uid = $_SESSION['user_id'] ?? null;

        // check that user id is a number
        if (!$uid || !is_numeric($uid)) {
            Response::error('Not logged in', 401);
        }

        // check if user exists (searh db)
        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $user = DB::one($pdo, $sql, ["user_id" => $uid]);

        if (!$user) {
            Response::error('User not found', 401);
        }

        return (int)$uid;
    }

    public static function getUser(PDO $pdo): bool
    {
        session_start();

        $uid = $_SESSION['user_id'] ?? null;

        // check that user id is a number
        if (!$uid || !is_numeric($uid)) {
            Response::error('Not logged in', 401);
        }

        // check if user exists (searh db)
        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $user = DB::one($pdo, $sql, ["user_id" => $uid]);

        return $user ? $user : false;
    }
}
