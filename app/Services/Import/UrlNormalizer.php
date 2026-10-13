<?php

namespace App\Services\Import;

class UrlNormalizer
{
    public static function isValid(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $parts = parse_url(trim($url));

        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return ! empty($parts['host']);
    }

    /**
     * Normalize a URL for duplicate detection ONLY. The original, as-entered
     * URL is always stored untouched — this value never reaches the UI.
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            return strtolower(trim($url));
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^(www\.|m\.)/', '', $host);

        $path = $parts['path'] ?? '';
        $path = $path !== '/' ? rtrim($path, '/') : '';

        $query = self::normalizeQuery($parts['query'] ?? '');

        return "{$scheme}://{$host}{$path}{$query}";
    }

    private static function normalizeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        $strippedParams = collect(config('lucky-catch-draw.tracking_params', []))
            ->map(fn (string $p) => strtolower($p))
            ->all();

        $strippedPrefixes = collect(config('lucky-catch-draw.tracking_param_prefixes', []))
            ->map(fn (string $p) => strtolower($p))
            ->all();

        $filtered = [];

        foreach ($params as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $strippedParams, true)) {
                continue;
            }

            if (collect($strippedPrefixes)->contains(fn (string $prefix) => str_starts_with($lowerKey, $prefix))) {
                continue;
            }

            $filtered[$key] = $value;
        }

        if ($filtered === []) {
            return '';
        }

        ksort($filtered);

        return '?'.http_build_query($filtered);
    }
}
