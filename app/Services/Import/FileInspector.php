<?php

namespace App\Services\Import;

class FileInspector
{
    private const ZIP_SIGNATURE = "PK\x03\x04";

    public static function sha256(string $absolutePath): string
    {
        return hash_file('sha256', $absolutePath);
    }

    public static function looksLikeXlsx(string $absolutePath): bool
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, 4);
        fclose($handle);

        return $bytes === self::ZIP_SIGNATURE;
    }

    public static function extension(string $originalFilename): string
    {
        return strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    }
}
