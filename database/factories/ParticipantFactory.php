<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        $url = 'https://facebook.com/'.fake()->unique()->userName();

        return [
            'campaign_id' => Campaign::factory(),
            'name' => $name,
            'profile_url' => $url,
            'profile_url_normalized' => strtolower($url),
            'eligible' => true,
            'is_winner' => false,
        ];
    }

    public function ineligible(string $note = 'Flagged as ineligible'): static
    {
        return $this->state(fn () => [
            'eligible' => false,
            'eligibility_note' => $note,
        ]);
    }

    public function winner(): static
    {
        return $this->state(fn () => [
            'is_winner' => true,
        ]);
    }
}
