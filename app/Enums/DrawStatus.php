<?php

namespace App\Enums;

enum DrawStatus: string
{
    case Valid = 'valid';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Voided => 'Voided',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Voided => 'danger',
        };
    }
}
