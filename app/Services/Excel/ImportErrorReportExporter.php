<?php

namespace App\Services\Excel;

use App\Models\Import;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportErrorReportExporter
{
    public static function download(Import $import): StreamedResponse
    {
        return response()->streamDownload(function () use ($import) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Row Number', 'Status', 'Name', 'Profile URL', 'Message',
            ]));

            $import->rows()
                ->whereIn('status', ['duplicate', 'rejected'])
                ->orderBy('row_number')
                ->chunk(500, function (Collection $chunk) use ($writer) {
                    foreach ($chunk as $row) {
                        $writer->addRow(Row::fromValues([
                            (string) $row->row_number,
                            $row->status->value,
                            ExcelSafe::cell($row->name),
                            ExcelSafe::cell($row->profile_url),
                            ExcelSafe::cell(implode('; ', $row->errors ?? [])),
                        ]));
                    }
                });

            $writer->close();
        }, 'import-'.$import->id.'-errors.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
