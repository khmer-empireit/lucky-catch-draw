<?php

use App\Models\Campaign;
use App\Models\Participant;
use App\Services\Excel\ParticipantsExporter;
use OpenSpout\Reader\XLSX\Reader;

it('escapes a formula-injection participant name in the real exported xlsx file', function () {
    $campaign = Campaign::factory()->create();
    Participant::factory()->create([
        'campaign_id' => $campaign->id,
        'name' => '=cmd|\' /C calc\'!A1',
        'profile_url' => 'https://facebook.com/safe',
    ]);

    $response = ParticipantsExporter::download($campaign);

    ob_start();
    $response->sendContent();
    $contents = ob_get_clean();

    $tmpPath = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpPath, $contents);

    $reader = new Reader();
    $reader->open($tmpPath);

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($tmpPath);

    expect($rows[1][0])->toStartWith("'=");
});
