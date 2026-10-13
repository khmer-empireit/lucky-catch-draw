<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Mapped = 'mapped';
    case Validating = 'validating';
    case Validated = 'validated';
    case Importing = 'importing';
    case Imported = 'imported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Mapped => 'Mapped',
            self::Validating => 'Validating',
            self::Validated => 'Validated',
            self::Importing => 'Importing',
            self::Imported => 'Imported',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Uploaded, self::Mapped => 'gray',
            self::Validating, self::Importing => 'warning',
            self::Validated => 'info',
            self::Imported => 'success',
            self::Failed => 'danger',
        };
    }
}
