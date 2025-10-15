<?php

declare(strict_types=1);

namespace App\Lib;

final class Image
{
    public static function uploadOriginal(
        string $tmp_name,
        string $original_name,
        string $dest_dir = '/uploads'
    ): string
    {
        // ensure directory exists
        if (!is_dir($dest_dir)) {
            throw new \RuntimeException("Destination directory does not exist: $dest_dir");
        }

        // sanitize and get extension
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]+$/i', $ext)) {
            throw new \RuntimeException("Invalid file extension: $ext");
        }

        // generate unique filename
        $filename = uniqid('photo_', true) . '.' . $ext;
        $full_path = rtrim($dest_dir, '/') . '/' . $filename;

        // move uploaded file
        if (!move_uploaded_file($tmp_name, $full_path)) {
            throw new \RuntimeException("Failed to move uploaded file.");
        }

        return $filename; // return just the filename (not full path)
    }

    // Resize & save JPEG; returns relative path 
    // LEARN: understand img handling/resizing
    public static function makeResized(
        string $src_dir,
        string $filename,
        string $dest_dir = '/uploads',
        int    $max_w = 800,
        int    $max_h = 800
    ): string
    {
        $src_tmp = rtrim($src_dir, '/') . '/' . $filename;
        $dest = rtrim($dest_dir, '/')."/$filename";

        // make sure the file exists
        if (!file_exists($src_tmp)) {
            throw new \RuntimeException("Source file not found for resizing: $src_tmp");
        }

        // make sure destination exists
        if (!is_dir($dest_dir)) {
            throw new \RuntimeException("Destination directory does not exist: $dest_dir");
        }


        $src_img = @imagecreatefromjpeg($src_tmp)
                  ?: throw new \RuntimeException("Bad JPEG");

        [$w,$h] = [imagesx($src_img), imagesy($src_img)];

        $scale  = min($max_w/$w, $max_h/$h, 1);

        $new_w = (int)($w*$scale);
        $new_h = (int)($h*$scale);

        $dst_img = imagecreatetruecolor($new_w, $new_h);
        imagecopyresampled($dst_img, $src_img, 0,0,0,0,
                           $new_w, $new_h, $w, $h);
        imagejpeg($dst_img, $dest);
        imagedestroy($src_img);
        imagedestroy($dst_img);
        return $filename;
    }
}
