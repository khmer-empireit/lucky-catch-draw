<?php

namespace App\Services\Import;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class SpreadsheetReaderFactory
{
    public static function forFile(string $absolutePath, string $originalFilename): ReaderInterface
    {
        $extension = FileInspector::extension($originalFilename);

        return $extension === 'csv' ? new CsvReader() : new XlsxReader();
    }
}
