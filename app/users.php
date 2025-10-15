<?php

namespace App;

use App\Lib\DB;
use App\Lib\Response;
use PDOException;

require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

require_once __DIR__ . '/config/db.php';

try {
    $sql = "SELECT user_id, name FROM users ORDER BY name ASC";
    $users = DB::all($pdo, $sql);

    Response::ok(['users' => $users]);
} catch (PDOException $e) {
    Response::error('Failed to fetch users', 500);
}