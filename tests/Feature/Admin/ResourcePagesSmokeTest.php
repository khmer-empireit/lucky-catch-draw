<?php

use App\Enums\RoleName;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Pages\ImportWizard;
use App\Filament\Resources\Draws\DrawResource;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::SuperAdmin->value);
});

it('renders the campaign list, create, view and edit pages', function () {
    $campaign = Campaign::factory()->ready()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $this->get(CampaignResource::getUrl('index'))->assertOk();
    $this->get(CampaignResource::getUrl('create'))->assertOk();
    $this->get(CampaignResource::getUrl('view', ['record' => $campaign]))->assertOk()->assertSee($campaign->name);
    $this->get(CampaignResource::getUrl('edit', ['record' => $campaign]))->assertOk();
});

it('renders the import wizard upload step for a campaign manager', function () {
    $manager = User::factory()->create();
    $manager->assignRole(RoleName::CampaignManager->value);

    $campaign = Campaign::factory()->create();

    $this->actingAs($manager)
        ->get(ImportWizard::getUrl(['record' => $campaign]))
        ->assertOk()
        ->assertSee('Upload a spreadsheet');
});

it('blocks a viewer from the import wizard', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(RoleName::Viewer->value);

    $campaign = Campaign::factory()->create();

    $this->actingAs($viewer)
        ->get(ImportWizard::getUrl(['record' => $campaign]))
        ->assertForbidden();
});

it('renders the draw list and view pages', function () {
    $campaign = Campaign::factory()->ready()->create();
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
    $draw = Draw::factory()->create([
        'campaign_id' => $campaign->id,
        'participant_id' => $participant->id,
        'drawn_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $this->get(DrawResource::getUrl('index'))->assertOk();
    $this->get(DrawResource::getUrl('view', ['record' => $draw]))->assertOk()->assertSee($participant->name);
});

it('renders the audit log and user management pages', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/audit-logs')->assertOk();
    $this->get('/admin/users')->assertOk();
    $this->get('/admin/users/create')->assertOk();
});

it('blocks a non super-admin from user management', function () {
    $manager = User::factory()->create();
    $manager->assignRole(RoleName::CampaignManager->value);

    $this->actingAs($manager)
        ->get('/admin/users')
        ->assertForbidden();
});
