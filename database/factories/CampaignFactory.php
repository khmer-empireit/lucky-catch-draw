<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'start_at' => now(),
            'end_at' => now()->addWeek(),
            'winners_to_select' => fake()->numberBetween(1, 5),
            'allow_repeat_winners' => false,
            'prize_name' => fake()->words(3, true),
            'status' => CampaignStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Ready,
            'participants_locked_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::InProgress,
            'participants_locked_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Completed,
            'participants_locked_at' => now(),
        ]);
    }
}
