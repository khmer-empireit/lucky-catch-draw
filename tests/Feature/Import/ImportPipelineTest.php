<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Jobs\Import\CommitImportJob;
use App\Jobs\Import\ValidateImportJob;
use App\Models\Campaign;
use App\Models\Import;
use App\Models\Participant;
use App\Services\Import\ColumnMapper;
use App\Services\Import\FileInspector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function makeXlsxFixture(array $rows, string $relativePath): string
{
    Storage::disk('local')->makeDirectory(dirname($relativePath));
    $absolutePath = Storage::disk('local')->path($relativePath);

    $writer = new Writer();
    $writer->openToFile($absolutePath);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $absolutePath;
}

function makeImportFor(Campaign $campaign, array $rows, array $columnMap, ?string $relativePath = null): Import
{
    $relativePath ??= 'imports/'.Str::uuid().'.xlsx';
    $absolutePath = makeXlsxFixture($rows, $relativePath);

    return Import::factory()->create([
        'campaign_id' => $campaign->id,
        'original_file_name' => 'participants.xlsx',
        'stored_path' => $relativePath,
        'file_sha256' => FileInspector::sha256($absolutePath),
        'column_map' => $columnMap,
        'status' => ImportStatus::Mapped,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

it('streams a spreadsheet into import_rows and classifies valid, duplicate and rejected rows', function () {
    $campaign = Campaign::factory()->create();

    $rows = [
        ['Participant Name', 'Profile URL', 'Email', 'Phone Number', 'Participant ID'],
        ['Sok Dara', 'https://facebook.com/sok.dara', 'sok@example.com', '012345678', 'P-1'],
        ['Jane Smith', 'https://www.facebook.com/jane.smith/', '', '', 'P-2'],
        ['No URL Person', '', '', '', 'P-3'],
        ['Dup Of Dara', 'https://m.facebook.com/sok.dara', '', '', 'P-4'],
        ['Bad Email', 'https://facebook.com/bademail', 'not-an-email', '', 'P-5'],
    ];

    $import = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]));

    (new ValidateImportJob($import->id))->handle();

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Validated);
    expect($import->total_rows)->toBe(5);
    expect($import->valid_rows)->toBe(2);
    expect($import->duplicate_rows)->toBe(1);
    expect($import->rejected_rows)->toBe(2);

    $dup = $import->rows()->where('name', 'Dup Of Dara')->first();
    expect($dup->status)->toBe(ImportRowStatus::Duplicate);

    $noUrl = $import->rows()->where('name', 'No URL Person')->first();
    expect($noUrl->status)->toBe(ImportRowStatus::Rejected);
    expect($noUrl->errors)->toContain('Profile URL is required.');

    $badEmail = $import->rows()->where('name', 'Bad Email')->first();
    expect($badEmail->status)->toBe(ImportRowStatus::Rejected);
});

it('detects duplicates against existing campaign participants, not just within the file', function () {
    $campaign = Campaign::factory()->create();

    Participant::factory()->create([
        'campaign_id' => $campaign->id,
        'profile_url' => 'https://facebook.com/existing.person',
        'profile_url_normalized' => 'https://facebook.com/existing.person',
        'external_id' => 'EXT-1',
    ]);

    $rows = [
        ['Participant Name', 'Profile URL', 'Participant ID'],
        ['Existing Person Again', 'https://www.facebook.com/existing.person/', 'EXT-9'],
        ['Different Person Same Id', 'https://facebook.com/someone.else', 'EXT-1'],
    ];

    $import = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]));

    (new ValidateImportJob($import->id))->handle();
    $import->refresh();

    expect($import->duplicate_rows)->toBe(2);
    expect($import->valid_rows)->toBe(0);
});

it('allows re-importing the exact same file into the same campaign', function () {
    $campaign = Campaign::factory()->create();
    $rows = [['Participant Name', 'Profile URL'], ['A', 'https://facebook.com/a']];

    $first = makeImportFor($campaign, $rows, ['name' => 0, 'profile_url' => 1], 'imports/one.xlsx');
    $second = makeImportFor($campaign, $rows, ['name' => 0, 'profile_url' => 1], 'imports/two.xlsx');

    expect($second->id)->not->toBe($first->id);
    expect($second->file_sha256)->toBe($first->file_sha256);
});

it('still catches every row as a duplicate when the same file is validated twice for one campaign', function () {
    $campaign = Campaign::factory()->create();
    $rows = [
        ['Participant Name', 'Profile URL'],
        ['A', 'https://facebook.com/a'],
        ['B', 'https://facebook.com/b'],
    ];

    $first = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]), 'imports/one.xlsx');
    (new ValidateImportJob($first->id))->handle();
    (new CommitImportJob($first->id))->handle(app(\App\Services\Audit\Auditor::class));

    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(2);

    // Re-uploading the identical file is no longer blocked at the file
    // level, but row-level duplicate detection against the participants
    // already committed still catches it — nothing gets double-imported.
    $second = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]), 'imports/two.xlsx');
    (new ValidateImportJob($second->id))->handle();
    $second->refresh();

    expect($second->valid_rows)->toBe(0);
    expect($second->duplicate_rows)->toBe(2);
    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(2);
});

it('allows the same file to be imported into a different campaign', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();
    $rows = [['Participant Name', 'Profile URL'], ['A', 'https://facebook.com/a']];

    $importA = makeImportFor($campaignA, $rows, ['name' => 0, 'profile_url' => 1], 'imports/one.xlsx');
    $importB = makeImportFor($campaignB, $rows, ['name' => 0, 'profile_url' => 1], 'imports/two.xlsx');

    expect($importA->file_sha256)->toBe($importB->file_sha256);
});

it('commits only valid rows into participants inside a single all-or-nothing transaction', function () {
    $campaign = Campaign::factory()->create();

    $rows = [
        ['Participant Name', 'Profile URL'],
        ['Valid One', 'https://facebook.com/valid.one'],
        ['Valid Two', 'https://facebook.com/valid.two'],
        ['No Url', ''],
    ];

    $import = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]));
    (new ValidateImportJob($import->id))->handle();
    $import->refresh();

    expect($import->valid_rows)->toBe(2);

    (new CommitImportJob($import->id))->handle(app(\App\Services\Audit\Auditor::class));

    expect($import->fresh()->status)->toBe(ImportStatus::Imported);
    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(2);
});

it('rolls back every participant when the commit fails partway through', function () {
    $campaign = Campaign::factory()->create();

    $rows = [['Participant Name', 'Profile URL']];
    for ($i = 1; $i <= 10; $i++) {
        $rows[] = ["Person {$i}", "https://facebook.com/person{$i}"];
    }

    $import = makeImportFor($campaign, $rows, ColumnMapper::guess($rows[0]));
    (new ValidateImportJob($import->id))->handle();
    $import->refresh();
    expect($import->valid_rows)->toBe(10);

    // Pre-seed a conflicting participant so the batch insert collides on the
    // unique (campaign_id, profile_url_normalized) constraint mid-transaction.
    Participant::factory()->create([
        'campaign_id' => $campaign->id,
        'profile_url' => 'https://facebook.com/person5',
        'profile_url_normalized' => 'https://facebook.com/person5',
    ]);

    expect(fn () => (new CommitImportJob($import->id))->handle(app(\App\Services\Audit\Auditor::class)))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed);
    // Only the pre-seeded conflicting participant survives; none of the 10 valid rows landed.
    expect(Participant::where('campaign_id', $campaign->id)->count())->toBe(1);
});
