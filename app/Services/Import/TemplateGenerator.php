<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateGenerator
{
    public static function download(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Participant Name', 'Profile URL', 'Email', 'Phone Number', 'Participant ID',
            ]));

            $writer->addRow(Row::fromValues([
                'Sok Dara', 'https://facebook.com/sok.dara', 'sok.dara@example.com', '+855 12 345 678', 'P-0001',
            ]));

            $writer->addRow(Row::fromValues([
                'Jane Smith', 'https://www.facebook.com/jane.smith', 'jane@example.com', '+1 555 123 4567', 'P-0002',
            ]));

            $writer->close();
        }, 'participants-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
