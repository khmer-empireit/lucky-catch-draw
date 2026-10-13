<?php

use App\Enums\CampaignStatus;
use App\Enums\DrawStatus;
use App\Enums\RoleName;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use App\Services\Draw\DrawService;
use App\Services\Draw\Exceptions\DrawNotAllowedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
    Role::findOrCreate(RoleName::CampaignManager->value, 'web');
    Role::findOrCreate(RoleName::Viewer->value, 'web');

    $this->manager = User::factory()->create();
    $this->manager->assignRole(RoleName::CampaignManager->value);

    $this->service = app(DrawService::class);
});

function lockedCampaign(int $winners = 3, int $participants = 10, bool $allowRepeat = false): Campaign
{
    $campaign = Campaign::factory()->ready()->create([
        'winners_to_select' => $winners,
        'allow_repeat_winners' => $allowRepeat,
    ]);

    Participant::factory()->count($participants)->create(['campaign_id' => $campaign->id]);

    return $campaign;
}

it('selects the winner on the server and never trusts client-chosen participants', function () {
    $campaign = lockedCampaign();

    $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());

    expect($draw)->toBeInstanceOf(Draw::class)
        ->and($draw->status)->toBe(DrawStatus::Valid)
        ->and($campaign->participants->pluck('id'))->toContain($draw->participant_id);

    expect(Participant::find($draw->participant_id)->is_winner)->toBeTrue();
});

it('returns the same draw when called twice with the same idempotency key', function () {
    $campaign = lockedCampaign();
    $key = (string) Str::uuid();

    $first = $this->service->draw($campaign, $this->manager, $key);
    $second = $this->service->draw($campaign, $this->manager, $key);

    expect($second->id)->toBe($first->id);
    expect(Draw::count())->toBe(1);
});

it('completes the campaign after the last winner is drawn', function () {
    $campaign = lockedCampaign(winners: 2, participants: 5);

    $this->service->draw($campaign, $this->manager, (string) Str::uuid());
    expect($campaign->fresh()->status)->toBe(CampaignStatus::InProgress);

    $this->service->draw($campaign, $this->manager, (string) Str::uuid());
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});

it('never picks the same winner twice unless repeat winners are allowed', function () {
    $campaign = lockedCampaign(winners: 3, participants: 3);

    $winners = [];
    for ($i = 0; $i < 3; $i++) {
        $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());
        $winners[] = $draw->participant_id;
    }

    expect($winners)->toHaveCount(3)->and(array_unique($winners))->toHaveCount(3);

    expect(fn () => $this->service->draw($campaign, $this->manager, (string) Str::uuid()))
        ->toThrow(DrawNotAllowedException::class);
});

it('allows repeat winners when the campaign opts in', function () {
    $campaign = lockedCampaign(winners: 4, participants: 1, allowRepeat: true);

    for ($i = 0; $i < 4; $i++) {
        $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());
        expect($draw->status)->toBe(DrawStatus::Valid);
    }

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});

it('refuses to draw when the participant list is not locked', function () {
    $campaign = Campaign::factory()->create(['winners_to_select' => 1]);
    Participant::factory()->create(['campaign_id' => $campaign->id]);

    expect(fn () => $this->service->draw($campaign, $this->manager, (string) Str::uuid()))
        ->toThrow(DrawNotAllowedException::class);
});

it('refuses to draw for a viewer', function () {
    $campaign = lockedCampaign();
    $viewer = User::factory()->create();
    $viewer->assignRole(RoleName::Viewer->value);

    expect(fn () => $this->service->draw($campaign, $viewer, (string) Str::uuid()))
        ->toThrow(DrawNotAllowedException::class);
});

it('fills the freed sequence slot on redraw after a void, instead of the next slot', function () {
    $campaign = lockedCampaign(winners: 3, participants: 5);

    $draw1 = $this->service->draw($campaign, $this->manager, (string) Str::uuid());
    $draw2 = $this->service->draw($campaign, $this->manager, (string) Str::uuid());
    expect($draw2->sequence)->toBe(2);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);
    $this->service->void($draw2, $admin, 'Winner disqualified');

    $redraw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());

    expect($redraw->sequence)->toBe(2)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::InProgress);
});

it('clears the winner flag on void unless the participant has another valid win', function () {
    $campaign = lockedCampaign(winners: 1, participants: 5, allowRepeat: true);

    $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());
    $winnerId = $draw->participant_id;
    expect(Participant::find($winnerId)->is_winner)->toBeTrue();

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);
    $this->service->void($draw, $admin, 'Test void');

    expect(Participant::find($winnerId)->is_winner)->toBeFalse();
    expect($draw->fresh()->status)->toBe(DrawStatus::Voided);
});

it('refuses void for a non super-admin', function () {
    $campaign = lockedCampaign(winners: 1, participants: 3);
    $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());

    expect(fn () => $this->service->void($draw, $this->manager, 'nope'))
        ->toThrow(DrawNotAllowedException::class);

    expect($draw->fresh()->status)->toBe(DrawStatus::Valid);
});

it('writes audit entries for draws and voids', function () {
    $campaign = lockedCampaign(winners: 1, participants: 3);
    $draw = $this->service->draw($campaign, $this->manager, (string) Str::uuid());

    expect(DB::table('audit_logs')->where('action', 'draw.completed')->count())->toBe(1);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);
    $this->service->void($draw, $admin, 'Audit check');

    expect(DB::table('audit_logs')->where('action', 'draw.voided')->count())->toBe(1);
});

it('rejects a second valid draw for the same slot at the database level', function () {
    $campaign = lockedCampaign(winners: 2, participants: 5);
    $participants = $campaign->participants;

    DB::table('draws')->insert([
        'campaign_id' => $campaign->id,
        'sequence' => 1,
        'participant_id' => $participants[0]->id,
        'pool_size' => 5,
        'pool_hash' => hash('sha256', '1'),
        'pool_snapshot' => json_encode([1]),
        'drawn_by' => $this->manager->id,
        'drawn_at' => now(),
        'status' => 'valid',
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('draws')->insert([
        'campaign_id' => $campaign->id,
        'sequence' => 1,
        'participant_id' => $participants[1]->id,
        'pool_size' => 5,
        'pool_hash' => hash('sha256', '1'),
        'pool_snapshot' => json_encode([1]),
        'drawn_by' => $this->manager->id,
        'drawn_at' => now(),
        'status' => 'valid',
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('returns the existing draw when a unique-constraint race is hit on insert', function () {
    $campaign = lockedCampaign(winners: 2, participants: 5);

    $intruderDraw = null;
    $fired = false;
    $eventName = 'eloquent.creating: '.Draw::class;

    Event::listen($eventName, function (Draw $draw) use (&$fired, &$intruderDraw, $campaign) {
        if ($fired) {
            return;
        }
        $fired = true;

        $intruderId = DB::table('draws')->insertGetId([
            'campaign_id' => $campaign->id,
            'sequence' => $draw->sequence,
            'participant_id' => $draw->participant_id,
            'pool_size' => $draw->pool_size,
            'pool_hash' => $draw->pool_hash,
            'pool_snapshot' => json_encode($draw->pool_snapshot),
            'drawn_by' => $draw->drawn_by,
            'drawn_at' => now(),
            'status' => 'valid',
            'idempotency_key' => 'racing-intruder-'.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $intruderDraw = Draw::find($intruderId);
    });

    try {
        $result = $this->service->draw($campaign, $this->manager, (string) Str::uuid());

        expect($intruderDraw)->not->toBeNull();
        expect($result->id)->toBe($intruderDraw->id);
        expect(Draw::where('campaign_id', $campaign->id)->where('status', 'valid')->count())->toBe(1);
    } finally {
        Event::forget($eventName);
    }
});
