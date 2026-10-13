<?php

use App\Enums\RoleName;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->manager = User::factory()->create();
    $this->manager->assignRole(RoleName::CampaignManager->value);
});

it('renders the draw screen with no participant pii beyond name and url', function () {
    $campaign = Campaign::factory()->ready()->create(['winners_to_select' => 2]);
    Participant::factory()->count(5)->create([
        'campaign_id' => $campaign->id,
        'email' => 'secret@example.com',
        'phone' => '0123456789',
    ]);

    $response = $this->actingAs($this->manager)->get("/draw/{$campaign->id}");

    $response->assertOk();
    $page = $response->viewData('page');

    expect($page['component'])->toBe('Draw/Show');
    expect($page['props']['eligibleCount'])->toBe(5);
    expect($page['props']['sample'])->not->toBeEmpty();
    foreach ($page['props']['sample'] as $row) {
        expect($row)->toHaveKeys(['id', 'name', 'profile_url']);
        expect($row)->not->toHaveKey('email');
        expect($row)->not->toHaveKey('phone');
    }
});

it('blocks viewing a draft campaigns draw screen', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs($this->manager)
        ->get("/draw/{$campaign->id}")
        ->assertForbidden();
});

it('runs a real draw through the http endpoint and never trusts a client-supplied winner', function () {
    $campaign = Campaign::factory()->ready()->create(['winners_to_select' => 1]);
    $participants = Participant::factory()->count(5)->create(['campaign_id' => $campaign->id]);

    $response = $this->actingAs($this->manager)->postJson("/draw/{$campaign->id}", [
        'idempotency_key' => (string) Str::uuid(),
        // Even if a malicious client tried to smuggle a winner, the service ignores it.
        'participant_id' => $participants->first()->id + 999,
        'winner_id' => 999999,
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['draw_id', 'sequence', 'winner' => ['id', 'name', 'profile_url'], 'pool_size', 'pool_hash', 'winners_remaining']);

    $winnerId = $response->json('winner.id');
    expect($participants->pluck('id'))->toContain($winnerId);
});

it('rate limits draw requests to 10 per minute per user', function () {
    $campaign = Campaign::factory()->ready()->create(['winners_to_select' => 50]);
    Participant::factory()->count(50)->create(['campaign_id' => $campaign->id]);

    $this->actingAs($this->manager);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson("/draw/{$campaign->id}", ['idempotency_key' => (string) Str::uuid()])->assertOk();
    }

    $this->postJson("/draw/{$campaign->id}", ['idempotency_key' => (string) Str::uuid()])
        ->assertStatus(429);
});

it('forbids a viewer from posting a draw', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(RoleName::Viewer->value);

    $campaign = Campaign::factory()->ready()->create();
    Participant::factory()->create(['campaign_id' => $campaign->id]);

    $this->actingAs($viewer)
        ->postJson("/draw/{$campaign->id}", ['idempotency_key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('returns the latest valid draw for reload recovery', function () {
    $campaign = Campaign::factory()->ready()->create(['winners_to_select' => 1]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->manager)->getJson("/draw/{$campaign->id}/latest");

    $response->assertOk();
    expect($response->json('draw.winner.id'))->toBe($participant->id);
});

it('renders the printable winner report', function () {
    $campaign = Campaign::factory()->ready()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    $draw = Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->manager)->get("/draws/{$draw->id}/print");

    $response->assertOk();
    $page = $response->viewData('page');
    expect($page['component'])->toBe('Draws/Print');
    expect($page['props']['draw']['winner']['name'])->toBe($participant->name);
});
