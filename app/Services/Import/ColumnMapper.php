<?php

namespace App\Services\Import;

class ColumnMapper
{
    public const REQUIRED_FIELDS = ['name', 'profile_url'];

    public const ALL_FIELDS = ['name', 'profile_url', 'email', 'phone', 'external_id'];

    private const FIELD_ALIASES = [
        'name' => ['participant name', 'name', 'full name'],
        'profile_url' => ['profile url', 'profile link', 'url', 'link', 'social profile'],
        'email' => ['email', 'email address'],
        'phone' => ['phone number', 'phone', 'mobile', 'mobile number', 'contact number'],
        'external_id' => ['participant id', 'id', 'external id', 'reference id'],
    ];

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int> field => column index
     */
    public static function guess(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = self::normalizeHeader((string) $header);

            foreach (self::FIELD_ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }

                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    public static function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $header) ?? ''));
    }
}
