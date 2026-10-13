<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Valid = 'valid';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Duplicate => 'Duplicate',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Duplicate => 'warning',
            self::Rejected => 'danger',
        };
    }
}
