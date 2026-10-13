<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Import;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(RoleName::SuperAdmin->value);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(RoleName::CampaignManager->value);

    $this->viewer = User::factory()->create();
    $this->viewer->assignRole(RoleName::Viewer->value);
});

it('lets every role view campaigns, participants, draws and audit logs read-only', function () {
    $campaign = Campaign::factory()->create();

    foreach (['superAdmin', 'manager', 'viewer'] as $role) {
        expect($this->{$role}->can('view', $campaign))->toBeTrue();
        expect($this->{$role}->can('viewAny', Campaign::class))->toBeTrue();
        expect($this->{$role}->can('viewAny', AuditLog::class))->toBeTrue();
    }
});

it('only lets super admin and campaign manager create and edit campaigns', function () {
    expect($this->superAdmin->can('create', Campaign::class))->toBeTrue();
    expect($this->manager->can('create', Campaign::class))->toBeTrue();
    expect($this->viewer->can('create', Campaign::class))->toBeFalse();

    $campaign = Campaign::factory()->create();
    expect($this->superAdmin->can('update', $campaign))->toBeTrue();
    expect($this->manager->can('update', $campaign))->toBeTrue();
    expect($this->viewer->can('update', $campaign))->toBeFalse();
});

it('only lets super admin and campaign manager upload imports', function () {
    expect($this->superAdmin->can('create', Import::class))->toBeTrue();
    expect($this->manager->can('create', Import::class))->toBeTrue();
    expect($this->viewer->can('create', Import::class))->toBeFalse();
});

it('only lets super admin and campaign manager confirm the participant list', function () {
    $campaign = Campaign::factory()->create();
    Participant::factory()->create(['campaign_id' => $campaign->id]);

    expect($this->superAdmin->can('confirmParticipantList', $campaign))->toBeTrue();
    expect($this->manager->can('confirmParticipantList', $campaign))->toBeTrue();
    expect($this->viewer->can('confirmParticipantList', $campaign))->toBeFalse();
});

it('only lets super admin and campaign manager run a draw', function () {
    $campaign = Campaign::factory()->create();

    expect($this->superAdmin->can('draw', $campaign))->toBeTrue();
    expect($this->manager->can('draw', $campaign))->toBeTrue();
    expect($this->viewer->can('draw', $campaign))->toBeFalse();
});

it('only lets super admin void a draw or approve a redraw — campaign manager cannot', function () {
    $campaign = Campaign::factory()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    $draw = Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->manager->id,
    ]);

    expect($this->superAdmin->can('void', $campaign))->toBeTrue();
    expect($this->manager->can('void', $campaign))->toBeFalse();
    expect($this->viewer->can('void', $campaign))->toBeFalse();

    expect($this->superAdmin->can('void', $draw))->toBeTrue();
    expect($this->manager->can('void', $draw))->toBeFalse();
    expect($this->viewer->can('void', $draw))->toBeFalse();
});

it('only lets super admin and campaign manager cancel a campaign', function () {
    $campaign = Campaign::factory()->create();

    expect($this->superAdmin->can('cancel', $campaign))->toBeTrue();
    expect($this->manager->can('cancel', $campaign))->toBeTrue();
    expect($this->viewer->can('cancel', $campaign))->toBeFalse();
});

it('only lets super admin manage users', function () {
    expect($this->superAdmin->can('viewAny', User::class))->toBeTrue();
    expect($this->manager->can('viewAny', User::class))->toBeFalse();
    expect($this->viewer->can('viewAny', User::class))->toBeFalse();

    expect($this->superAdmin->can('create', User::class))->toBeTrue();
    expect($this->manager->can('create', User::class))->toBeFalse();
});

it('only lets super admin and campaign manager edit eligibility before a draw starts', function () {
    $campaign = Campaign::factory()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);

    expect($this->superAdmin->can('update', $participant))->toBeTrue();
    expect($this->manager->can('update', $participant))->toBeTrue();
    expect($this->viewer->can('update', $participant))->toBeFalse();
});

it('freezes participant edits once the list is locked and a draw exists', function () {
    $campaign = Campaign::factory()->ready()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->manager->id,
    ]);

    expect($this->superAdmin->can('update', $participant))->toBeFalse();
    expect($this->manager->can('update', $participant))->toBeFalse();
});

it('only allows deleting a campaign while it is still a draft', function () {
    $draft = Campaign::factory()->create();
    $ready = Campaign::factory()->ready()->create();

    expect($this->manager->can('delete', $draft))->toBeTrue();
    expect($this->manager->can('delete', $ready))->toBeFalse();
    expect($this->viewer->can('delete', $draft))->toBeFalse();
});
