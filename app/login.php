<?php

namespace App;

use App\Lib\DB;
use App\Lib\Response;

require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

session_start();

require_once __DIR__ . '/config/db.php';

// sanitize input
extract(get_sanitized_input());

if (!is_valid_input($email, $password)) {
    Response::error('Email and password are required and must be valid.', 400);
}

$user = get_credentials($pdo, $email);

if (!$user || !password_verify($password, $user['password_hash'])) {
    Response::error('Invalid email or password.', 401);
}

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['user_name'] = $user['name'];

Response::ok([
    'message' => 'Logged in successfully',
    'name' => $user['name']
]);


// HELPER FUNCTIONS

function get_sanitized_input() {
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    return ['email' => $email, 'password' => $password];
}

function is_valid_input($email, $password) {
    return $email && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password);
}

function get_credentials($pdo, $email) {
    $sql = "SELECT user_id, name, password_hash FROM users WHERE email = :email";
    $params = ['email' => $email];
    $user = DB::one($pdo, $sql, $params);

    return $user;
}