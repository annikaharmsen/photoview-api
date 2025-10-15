<?php

namespace App;

use App\Lib\Auth;
use App\Lib\DB;
use App\Lib\Response;
use App\Lib\Image;
use Exception;
use PDOException;

require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

require_once __DIR__ . '/config/db.php';

switch($_SERVER['REQUEST_METHOD']) {

    case "POST":
        uploadFiles($pdo);
        break;

    case "GET":
        getImages($pdo);
        break;
}

// HELPER FUNCTIONS

function get_sanitized_input() {
    $files = $_FILES['photos'] ?? null;
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $desc = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    return [$files, $user_id, $desc];
}

function uploadFiles($pdo) {
    [$files, $user_id, $desc] = get_sanitized_input();

    if (!$files || !is_array($files['name'])) {
        Response::error("No photo files received.", 400);
    }

    if (!$user_id) {
        Response::error("Invalid or missing user ID.", 400);
    }

    $upload_dir = '../uploads/'; 
    $resized_dir = "../uploads/optimized/"; 
    $uploaded = 0; 
    $skipped = 0; 
    $values = [];
    $params = [];

    for ($i = 0; $i < count($files['name']); $i++) {
        $name = $files['name'][$i];
        $type = $files['type'][$i]; // file MIME type
        $error = $files['error'][$i]; // file upload error code

        if ($error !== UPLOAD_ERR_OK || strpos($type, 'image/') !== 0) {
            error_log("Skipped file: $name | Type: $type | Error: $error\n", 3, '../uploads/upload_debug.log'); // log skipped file details
            $skipped++;
            continue;
        }

        try {
            $filename = attempt_file_upload($files, $i, $upload_dir, $resized_dir);

            $values[] = "(?, ?, ?)";
            $params[] = $user_id;
            $params[] = $filename;
            $params[] = $desc ?? '';
            $uploaded++;

        } catch (Exception $e) {
            error_log("Upload failed for {$files['name'][$i]}: " . $e->getMessage(), 3, '../uploads/upload_debug.log');
            $skipped++;
            continue;
        }
    }

    if (!empty($values)) {
        batch_insert($pdo, $values, $params);
    }

    Response::ok([
        'uploaded' => $uploaded,
        'skipped' => $skipped,
        'message' => "Uploaded $uploaded photo(s). Skipped $skipped."
    ]);
}

function attempt_file_upload($files, $fileIndex, $upload_dir, $resized_dir) {
    $filename = Image::uploadOriginal($files['tmp_name'][$fileIndex], $files['name'][$fileIndex], $upload_dir);

    Image::makeResized($upload_dir, $filename, $resized_dir);

    return $filename;
}

function batch_insert($pdo, $values, $params) {
    $sql = "INSERT INTO photos (user_id, image_url, description) VALUES " . implode(", ", $values);

    DB::insert($pdo, $sql, $params);
}

function getImages($pdo) {

    $user_id = Auth::requireLogin($pdo);

    try {

        if (empty($_GET["photo_id"])) {
            $photos = get_all_photos($pdo, $user_id);

            Response::ok(['photos' => $photos]);
        } else {
            $photo = get_photo($pdo, $user_id);

            Response::ok(['photo' => $photo]);
        }

    } catch (PDOException $e) {
        Response::error('Database error:' . $e->getMessage(), 500);
    }
}


    function get_all_photos($pdo, $user_id) {
        $sql = "SELECT photo_id, image_url, description, uploaded_at 
            FROM photos 
            WHERE user_id = :user_id 
            ORDER BY uploaded_at 
            DESC";

        $photos = DB::all($pdo, $sql, [
            'user_id' => $user_id
        ]);

        return $photos;
    }

    function get_photo($pdo, $user_id) {
        $sql = "SELECT photo_id, image_url, description
            FROM photos
            WHERE photo_id = :photo_id
            AND user_id = :user_id
            LIMIT 1";

        $photo = DB::one($pdo, $sql, [
            "photo_id" => $_GET["photo_id"], 
            'user_id' => $user_id
        ]);

        return $photo;
    }