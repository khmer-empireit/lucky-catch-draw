<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'import_id',
        'name',
        'profile_url',
        'profile_url_normalized',
        'email',
        'phone',
        'external_id',
        'eligible',
        'eligibility_note',
        'is_winner',
    ];

    protected function casts(): array
    {
        return [
            'eligible' => 'boolean',
            'is_winner' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }
}
