<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'original_file_name',
        'stored_path',
        'file_sha256',
        'status',
        'column_map',
        'total_rows',
        'valid_rows',
        'duplicate_rows',
        'rejected_rows',
        'progress',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'rejected_rows' => 'integer',
            'progress' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}
