<?php

namespace Database\Factories;

use App\Enums\DrawStatus;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Draw>
 */
class DrawFactory extends Factory
{
    public function definition(): array
    {
        $campaign = Campaign::factory()->inProgress();
        $participant = Participant::factory();

        return [
            'campaign_id' => $campaign,
            'sequence' => 1,
            'participant_id' => $participant,
            'pool_size' => 1,
            'pool_hash' => hash('sha256', '1'),
            'pool_snapshot' => [1],
            'drawn_by' => User::factory(),
            'drawn_at' => now(),
            'status' => DrawStatus::Valid,
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }

    public function voided(string $reason = 'Winner disqualified'): static
    {
        return $this->state(fn () => [
            'status' => DrawStatus::Voided,
            'voided_reason' => $reason,
            'voided_by' => User::factory(),
            'voided_at' => now(),
        ]);
    }
}
