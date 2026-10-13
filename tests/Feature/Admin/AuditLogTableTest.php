<?php

use App\Enums\RoleName;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::SuperAdmin->value);
    $this->actingAs($this->admin);
});

it('renders the global audit log table with array-valued details without erroring', function () {
    $campaign = Campaign::factory()->create();

    AuditLog::create([
        'actor_id' => $this->admin->id,
        'action' => 'draw.completed',
        'auditable_type' => \App\Models\Draw::class,
        'auditable_id' => 1,
        'details' => ['sequence' => 1, 'pool_hash' => 'abc123', 'winners_remaining' => 2],
    ]);

    Livewire::test(ListAuditLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(AuditLog::all());
});

it('renders the campaign audit relation manager table without erroring', function () {
    $campaign = Campaign::factory()->create();

    AuditLog::create([
        'actor_id' => $this->admin->id,
        'action' => 'campaign.list_confirmed',
        'auditable_type' => Campaign::class,
        'auditable_id' => $campaign->id,
        'details' => ['eligible_count' => 42],
    ]);

    Livewire::test(\App\Filament\Resources\Campaigns\RelationManagers\AuditLogsRelationManager::class, [
        'ownerRecord' => $campaign,
        'pageClass' => ViewCampaign::class,
    ])->assertSuccessful();
});
