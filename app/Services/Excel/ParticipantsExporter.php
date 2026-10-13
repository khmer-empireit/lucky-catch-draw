<?php

namespace App\Services\Excel;

use App\Models\Campaign;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantsExporter
{
    public static function download(Campaign $campaign): StreamedResponse
    {
        $filename = 'participants-'.str($campaign->name)->slug().'.xlsx';

        return response()->streamDownload(function () use ($campaign) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Participant Name', 'Profile URL', 'Email', 'Phone Number', 'Participant ID', 'Eligible', 'Winner',
            ]));

            $campaign->participants()->orderBy('id')->chunk(500, function (Collection $chunk) use ($writer) {
                foreach ($chunk as $participant) {
                    $writer->addRow(Row::fromValues([
                        ExcelSafe::cell($participant->name),
                        ExcelSafe::cell($participant->profile_url),
                        ExcelSafe::cell($participant->email),
                        ExcelSafe::cell($participant->phone),
                        ExcelSafe::cell($participant->external_id),
                        $participant->eligible ? 'Yes' : 'No',
                        $participant->is_winner ? 'Yes' : 'No',
                    ]));
                }
            });

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
