<?php

namespace App\Services\Draw;

use App\Enums\CampaignStatus;
use App\Enums\DrawStatus;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use App\Services\Audit\Auditor;
use App\Services\Draw\Exceptions\DrawNotAllowedException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for winner selection.
 *
 * The winner is always chosen on the server, inside a locked transaction,
 * using random_int(). Callers (controllers, Filament actions, tests) only
 * ever receive the Draw this service created — nothing upstream chooses or
 * influences which participant wins.
 */
class DrawService
{
    public function __construct(private readonly Auditor $auditor)
    {
    }

    public function draw(Campaign $campaign, User $user, string $idempotencyKey): Draw
    {
        if ($existing = Draw::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($campaign, $user, $idempotencyKey) {
            /** @var Campaign $campaign */
            $campaign = Campaign::whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();

            $this->assertCanDraw($campaign, $user);

            $poolQuery = $campaign->eligibleParticipants();

            if (! $campaign->allow_repeat_winners) {
                $poolQuery->where('is_winner', false);
            }

            $ids = $poolQuery->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($ids === []) {
                throw new DrawNotAllowedException('There are no eligible participants left in the pool.');
            }

            $poolHash = hash('sha256', implode("\n", $ids));
            $sequence = $this->nextSequence($campaign);

            $winnerIndex = $this->pickWinnerIndex(count($ids));
            $winnerId = $ids[$winnerIndex];

            try {
                $draw = Draw::create([
                    'campaign_id' => $campaign->getKey(),
                    'sequence' => $sequence,
                    'participant_id' => $winnerId,
                    'pool_size' => count($ids),
                    'pool_hash' => $poolHash,
                    'pool_snapshot' => $ids,
                    'drawn_by' => $user->getKey(),
                    'drawn_at' => now(),
                    'status' => DrawStatus::Valid,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if ($raced = $this->resolveRace($e, $campaign, $sequence, $idempotencyKey)) {
                    return $raced;
                }

                throw $e;
            }

            Participant::whereKey($winnerId)->update(['is_winner' => true]);

            $winnersRemaining = $campaign->winners_to_select - $campaign->validDraws()->count();

            $campaign->status = $winnersRemaining <= 0 ? CampaignStatus::Completed : CampaignStatus::InProgress;
            $campaign->save();

            $this->auditor->log('draw.completed', $draw, [
                'campaign_id' => $campaign->getKey(),
                'sequence' => $sequence,
                'participant_id' => $winnerId,
                'pool_size' => count($ids),
                'pool_hash' => $poolHash,
                'winners_remaining' => max(0, $winnersRemaining),
            ], $user);

            return $draw->fresh();
        });
    }

    public function void(Draw $draw, User $user, string $reason): Draw
    {
        if (! $user->isSuperAdmin()) {
            throw new DrawNotAllowedException('Only a super admin may void a draw.');
        }

        return DB::transaction(function () use ($draw, $user, $reason) {
            /** @var Draw $draw */
            $draw = Draw::whereKey($draw->getKey())->lockForUpdate()->firstOrFail();

            if ($draw->status === DrawStatus::Voided) {
                return $draw;
            }

            $campaign = Campaign::whereKey($draw->campaign_id)->lockForUpdate()->firstOrFail();

            $draw->update([
                'status' => DrawStatus::Voided,
                'voided_reason' => $reason,
                'voided_by' => $user->getKey(),
                'voided_at' => now(),
            ]);

            $participant = Participant::whereKey($draw->participant_id)->lockForUpdate()->first();

            if ($participant) {
                $stillHasValidWin = Draw::where('participant_id', $participant->getKey())
                    ->where('status', DrawStatus::Valid)
                    ->exists();

                if (! $stillHasValidWin) {
                    $participant->update(['is_winner' => false]);
                }
            }

            if ($campaign->status === CampaignStatus::Completed || $campaign->status === CampaignStatus::Ready) {
                $campaign->status = CampaignStatus::InProgress;
                $campaign->save();
            }

            $this->auditor->log('draw.voided', $draw, [
                'reason' => $reason,
                'campaign_id' => $campaign->getKey(),
            ], $user);

            return $draw->fresh();
        });
    }

    /**
     * The single point where chance enters the system: a cryptographically
     * secure, uniformly distributed index into the pool. Exposed publicly so
     * its distribution can be verified directly without the overhead of
     * creating thousands of Draw rows.
     */
    public function pickWinnerIndex(int $poolSize): int
    {
        return random_int(0, $poolSize - 1);
    }

    private function assertCanDraw(Campaign $campaign, User $user): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Ready, CampaignStatus::InProgress], true)) {
            throw new DrawNotAllowedException('This campaign is not open for drawing.');
        }

        if (! $campaign->isLocked()) {
            throw new DrawNotAllowedException('The participant list has not been confirmed yet.');
        }

        if ($campaign->winnersRemaining() <= 0) {
            throw new DrawNotAllowedException('All winners for this campaign have already been drawn.');
        }

        if (! $user->canManageCampaigns()) {
            throw new DrawNotAllowedException('You are not allowed to run draws.');
        }
    }

    /**
     * Find the lowest sequence slot (1..winners_to_select) that isn't currently
     * held by a valid draw. Voiding a draw frees its slot for a later redraw
     * instead of appending past the end of the campaign's prize list.
     */
    private function nextSequence(Campaign $campaign): int
    {
        $used = $campaign->validDraws()->pluck('sequence')->map(fn ($s) => (int) $s)->all();

        for ($i = 1; $i <= $campaign->winners_to_select; $i++) {
            if (! in_array($i, $used, true)) {
                return $i;
            }
        }

        throw new DrawNotAllowedException('All winners for this campaign have already been drawn.');
    }

    private function resolveRace(QueryException $e, Campaign $campaign, int $sequence, string $idempotencyKey): ?Draw
    {
        if (! $this->isUniqueConstraintViolation($e)) {
            return null;
        }

        return Draw::where('idempotency_key', $idempotencyKey)->first()
            ?? Draw::where('campaign_id', $campaign->getKey())->where('active_sequence', $sequence)->first();
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
