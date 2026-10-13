<?php

namespace App\Models;

use App\Enums\DrawStatus;
use Database\Factories\DrawFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Draw extends Model
{
    /** @use HasFactory<DrawFactory> */
    use HasFactory;

    /**
     * The only attributes a void operation is allowed to change.
     * Every other field is permanent once a draw is recorded.
     */
    private const VOIDABLE_FIELDS = ['status', 'voided_reason', 'voided_by', 'voided_at'];

    protected $fillable = [
        'campaign_id',
        'sequence',
        'participant_id',
        'pool_size',
        'pool_hash',
        'pool_snapshot',
        'drawn_by',
        'drawn_at',
        'status',
        'voided_reason',
        'voided_by',
        'voided_at',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(function (Draw $draw) {
            $dirty = array_keys($draw->getDirty());

            if (array_diff($dirty, self::VOIDABLE_FIELDS) !== []) {
                throw new LogicException('Draws are immutable except for void fields.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Draws can never be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'pool_snapshot' => 'array',
            'status' => DrawStatus::class,
            'drawn_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function drawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
