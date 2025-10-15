<?php

declare(strict_types=1);

namespace App\Lib;

final class Response
{
    // success JSON wrapper 
    public static function ok(array $data = [], int $code = 200): never
    {
        self::send($data + ['success' => true], $code);
    }

    // error JSON wrapper
    public static function error(string $msg, int $code = 400): never
    {
        self::send(['success' => false, 'error' => $msg], $code);
    }

    public static function setCORSHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: POST, GET, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Credentials: true');
    }

    private static function send(array $payload, int $status): never
    {
        self::setCORSHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
