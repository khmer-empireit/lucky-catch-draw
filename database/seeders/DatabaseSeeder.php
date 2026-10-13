<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => config('lucky-catch-draw.admin_email')],
            [
                'name' => 'Super Admin',
                'password' => config('lucky-catch-draw.admin_password'),
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->call(DemoCampaignSeeder::class);
    }
}
