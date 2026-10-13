<?php

namespace Database\Factories;

use App\Enums\ImportRowStatus;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        $url = 'https://facebook.com/'.fake()->userName();

        return [
            'import_id' => Import::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 100000),
            'raw' => ['Participant Name' => $name, 'Profile URL' => $url],
            'name' => $name,
            'profile_url' => $url,
            'profile_url_normalized' => strtolower($url),
            'status' => ImportRowStatus::Valid,
        ];
    }

    public function rejected(string $message = 'Invalid data'): static
    {
        return $this->state(fn () => [
            'status' => ImportRowStatus::Rejected,
            'errors' => [$message],
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn () => [
            'status' => ImportRowStatus::Duplicate,
            'errors' => ['Duplicate profile URL'],
        ]);
    }
}
