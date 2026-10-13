<?php

namespace App\Services\Import;

class RowValidator
{
    /**
     * @return array<int, string> validation error messages, empty when the row is structurally valid
     */
    public static function validate(?string $name, ?string $profileUrl, ?string $email, ?string $phone): array
    {
        $errors = [];

        if ($name === null || trim($name) === '') {
            $errors[] = 'Name is required.';
        }

        if ($profileUrl === null || trim($profileUrl) === '') {
            $errors[] = 'Profile URL is required.';
        } elseif (! UrlNormalizer::isValid($profileUrl)) {
            $errors[] = 'Profile URL must be a valid http:// or https:// link.';
        }

        if ($email !== null && trim($email) !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }

        if ($phone !== null && trim($phone) !== '' && ! preg_match('/^[0-9+\-\s().]{6,20}$/', $phone)) {
            $errors[] = 'Phone number is invalid.';
        }

        return $errors;
    }
}
