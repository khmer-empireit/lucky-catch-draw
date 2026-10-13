<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'cover_image',
        'start_at',
        'end_at',
        'winners_to_select',
        'allow_repeat_winners',
        'prize_name',
        'prize_image',
        'status',
        'participants_locked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'winners_to_select' => 'integer',
            'allow_repeat_winners' => 'boolean',
            'status' => CampaignStatus::class,
            'participants_locked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function eligibleParticipants(): HasMany
    {
        return $this->participants()->where('eligible', true);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }

    public function validDraws(): HasMany
    {
        return $this->draws()->where('status', 'valid');
    }

    public function isLocked(): bool
    {
        return $this->participants_locked_at !== null;
    }

    public function winnersRemaining(): int
    {
        return max(0, $this->winners_to_select - $this->validDraws()->count());
    }

    /**
     * Every audit entry that touches this campaign, whether logged directly
     * against the campaign or against one of its imports/participants/draws
     * (every Auditor::log() call for those embeds `campaign_id` in details).
     * Not a true Eloquent relation, but Filament's relation managers accept
     * any Builder here.
     */
    public function auditLogs(): Builder
    {
        return AuditLog::query()
            ->where(function (Builder $query) {
                $query->where(function (Builder $direct) {
                    $direct->where('auditable_type', self::class)->where('auditable_id', $this->id);
                })->orWhere('details->campaign_id', $this->id);
            })
            ->latest('created_at');
    }
}
