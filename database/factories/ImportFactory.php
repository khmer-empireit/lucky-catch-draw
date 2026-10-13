<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'original_file_name' => 'participants.xlsx',
            'stored_path' => 'imports/'.fake()->uuid().'.xlsx',
            'file_sha256' => hash('sha256', fake()->uuid()),
            'status' => ImportStatus::Uploaded,
            'created_by' => User::factory(),
        ];
    }

    public function imported(): static
    {
        return $this->state(fn () => ['status' => ImportStatus::Imported]);
    }
}
