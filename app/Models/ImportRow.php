<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_number',
        'raw',
        'name',
        'profile_url',
        'profile_url_normalized',
        'email',
        'phone',
        'external_id',
        'status',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'errors' => 'array',
            'status' => ImportRowStatus::class,
            'row_number' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
