<?php

namespace App\Services\Excel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WinnersExporter
{
    public static function download(Builder $draws): StreamedResponse
    {
        return response()->streamDownload(function () use ($draws) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Campaign', 'Prize', 'Sequence', 'Winner Name', 'Profile URL', 'Drawn At', 'Drawn By', 'Status', 'Voided Reason',
            ]));

            $draws->with(['campaign', 'participant', 'drawnBy'])
                ->orderByDesc('drawn_at')
                ->chunk(500, function (Collection $chunk) use ($writer) {
                    foreach ($chunk as $draw) {
                        $writer->addRow(Row::fromValues([
                            ExcelSafe::cell($draw->campaign?->name),
                            ExcelSafe::cell($draw->campaign?->prize_name),
                            (string) $draw->sequence,
                            ExcelSafe::cell($draw->participant?->name),
                            ExcelSafe::cell($draw->participant?->profile_url),
                            $draw->drawn_at?->toDateTimeString() ?? '',
                            ExcelSafe::cell($draw->drawnBy?->name),
                            $draw->status->value,
                            ExcelSafe::cell($draw->voided_reason),
                        ]));
                    }
                });

            $writer->close();
        }, 'winners.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
