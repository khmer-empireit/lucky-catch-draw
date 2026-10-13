<?php

use App\Enums\RoleName;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('renders the admin dashboard for a super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);

    $campaign = Campaign::factory()->inProgress()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Lucky Catch Draw');
});

it('blocks an inactive user from accessing the panel', function () {
    $user = User::factory()->inactive()->create();
    $user->assignRole(RoleName::Viewer->value);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});
