<?php

namespace App;

use App\Lib\DB;
use App\Lib\Response;

require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

session_start();

require_once __DIR__ . '/config/db.php';

extract(get_sanitized_input());

if (!is_valid_input($name, $email, $password)) {
    Response::error('Please provide valid name, email, and password (min 6 characters).', 400);
}

$existing_user = get_existing_user($pdo, $email);

if ($existing_user) {
    Response::error('This email is already registered.', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$user_id = add_user($pdo, $name, $email, $hash);

$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $name;

Response::ok([
    'message' => 'Registration successful',
    'name' => $name
]);


//HELPER FUNCTIONS

function get_sanitized_input() {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';

    return [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        '$confirm_password' => $confirm_password
    ];
}

function is_valid_input($name, $email, $password) {
    return !empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL) && !strlen($password) < 6;
}

function get_existing_user($pdo, $email) {
    $sql = "SELECT user_id FROM users WHERE email = :email";
    $params = ['email' => $email];
    $existing_user = DB::one($pdo, $sql, $params);

    return $existing_user;
}

function add_user($pdo, $name, $email, $hash) {
    $sql = "INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)";
    $params = [
        'name' => $name,
        'email' => $email,
        'hash' => $hash
    ];

    return DB::insert($pdo, $sql, $params);
}