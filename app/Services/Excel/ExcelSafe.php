<?php

namespace App\Services\Excel;

class ExcelSafe
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralize formula-injection payloads before a value is ever written
     * into a spreadsheet cell. Every Excel export in the app must route
     * through this helper.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
