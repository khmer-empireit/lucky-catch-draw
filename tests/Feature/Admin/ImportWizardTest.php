<?php

use App\Enums\ImportStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Campaigns\Pages\ImportWizard;
use App\Models\Campaign;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;

function buildXlsxUpload(array $rows, string $filename = 'participants.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

    $writer = new Writer();
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent($filename, $content);
}

beforeEach(function () {
    foreach (RoleName::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('local');

    $this->manager = User::factory()->create();
    $this->manager->assignRole(RoleName::CampaignManager->value);
    $this->actingAs($this->manager);
});

it('walks a campaign manager through upload, mapping, validation, review and commit', function () {
    $campaign = Campaign::factory()->create();

    $upload = buildXlsxUpload([
        ['Participant Name', 'Profile URL', 'Email'],
        ['Sok Dara', 'https://facebook.com/sok.dara', 'sok@example.com'],
        ['Jane Smith', 'https://facebook.com/jane.smith', ''],
        ['Bad Row', '', ''],
    ]);

    $component = Livewire::test(ImportWizard::class, ['record' => $campaign->getKey()])
        ->set('data.file', $upload)
        ->call('goToMapping');

    $component->assertSet('step', 2);
    expect($component->get('headers'))->toContain('Participant Name', 'Profile URL', 'Email');
    expect($component->get('data.map_name'))->not->toBeNull();
    expect($component->get('data.map_profile_url'))->not->toBeNull();

    $component->call('startValidation');
    $component->assertSet('step', 3);

    $importId = $component->get('importId');
    expect($importId)->not->toBeNull();

    // QUEUE_CONNECTION=sync in the test environment, so startValidation()
    // already ran ValidateImportJob synchronously — no need to run it again.
    $import = \App\Models\Import::find($importId);
    expect($import->status)->toBe(ImportStatus::Validated);
    expect($import->valid_rows)->toBe(2);
    expect($import->rejected_rows)->toBe(1);

    $component->call('goToReview');
    $component->assertSet('step', 4);

    $component->call('confirmImport');
    $component->assertSet('step', 5);

    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(2);
    expect($import->fresh()->status)->toBe(ImportStatus::Imported);
});

it('allows uploading the same file twice into the same campaign', function () {
    $campaign = Campaign::factory()->create();
    $rows = [
        ['Participant Name', 'Profile URL'],
        ['Sok Dara', 'https://facebook.com/sok.dara'],
    ];

    $firstUpload = buildXlsxUpload($rows);
    Livewire::test(ImportWizard::class, ['record' => $campaign->getKey()])
        ->set('data.file', $firstUpload)
        ->call('goToMapping')
        ->call('startValidation')
        ->call('goToReview')
        ->call('confirmImport');

    expect(\App\Models\Import::where('campaign_id', $campaign->id)->count())->toBe(1);
    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(1);

    $secondUpload = buildXlsxUpload($rows);
    $component = Livewire::test(ImportWizard::class, ['record' => $campaign->getKey()])
        ->set('data.file', $secondUpload)
        ->call('goToMapping');

    // The second upload proceeds past step 1 (no file-level block)...
    $component->assertSet('step', 2);
    $component->call('startValidation');

    expect(\App\Models\Import::where('campaign_id', $campaign->id)->count())->toBe(2);

    // ...but its one row is still caught as a duplicate against the
    // participant already committed from the first import.
    $import = \App\Models\Import::where('campaign_id', $campaign->id)->latest('id')->first();
    expect($import->duplicate_rows)->toBe(1);
    expect($import->valid_rows)->toBe(0);
});

it('lets the reviewer exclude a valid row before confirming the import', function () {
    $campaign = Campaign::factory()->create();

    $upload = buildXlsxUpload([
        ['Participant Name', 'Profile URL'],
        ['Sok Dara', 'https://facebook.com/sok.dara'],
        ['Jane Smith', 'https://facebook.com/jane.smith'],
    ]);

    $component = Livewire::test(ImportWizard::class, ['record' => $campaign->getKey()])
        ->set('data.file', $upload)
        ->call('goToMapping')
        ->call('startValidation');

    $importId = $component->get('importId');

    $component->call('goToReview');

    $rowToExclude = \App\Models\ImportRow::where('import_id', $importId)->where('name', 'Jane Smith')->first();
    $component->call('excludeRow', $rowToExclude->id);

    $import = \App\Models\Import::find($importId);
    expect($import->valid_rows)->toBe(1);
    expect($import->rejected_rows)->toBe(1);

    $component->call('confirmImport');

    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(1);
    expect(Participant::where('campaign_id', $campaign->id)->first()->name)->toBe('Sok Dara');
});
