<?php

namespace App\Jobs\Import;

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Participant;
use App\Services\Import\RowValidator;
use App\Services\Import\SpreadsheetReaderFactory;
use App\Services\Import\UrlNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ValidateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public function __construct(public int $importId)
    {
    }

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);
        $import->update(['status' => ImportStatus::Validating, 'progress' => 0]);

        $absolutePath = Storage::disk('local')->path($import->stored_path);
        $columnMap = $import->column_map ?? [];

        $totalDataRows = $this->countDataRows($absolutePath, $import->original_file_name);

        if ($totalDataRows === 0) {
            $import->update(['status' => ImportStatus::Validated, 'total_rows' => 0, 'progress' => 100]);

            return;
        }

        $existingUrls = Participant::where('campaign_id', $import->campaign_id)
            ->pluck('profile_url_normalized')
            ->flip()
            ->all();

        $existingExternalIds = Participant::where('campaign_id', $import->campaign_id)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->flip()
            ->all();

        $seenUrls = [];
        $seenExternalIds = [];

        $totalRows = 0;
        $validCount = 0;
        $duplicateCount = 0;
        $rejectedCount = 0;
        $buffer = [];
        $rowNumber = 0;
        $processedDataRows = 0;

        $reader = SpreadsheetReaderFactory::forFile($absolutePath, $import->original_file_name);
        $reader->open($absolutePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;

                if ($rowNumber === 1) {
                    continue; // header row
                }

                $cells = $row->toArray();
                $totalRows++;
                $processedDataRows++;

                $name = $this->cell($cells, $columnMap['name'] ?? null);
                $profileUrl = $this->cell($cells, $columnMap['profile_url'] ?? null);
                $email = $this->cell($cells, $columnMap['email'] ?? null);
                $phone = $this->cell($cells, $columnMap['phone'] ?? null);
                $externalId = $this->cell($cells, $columnMap['external_id'] ?? null);

                $errors = RowValidator::validate($name, $profileUrl, $email, $phone);
                $normalizedUrl = ($profileUrl && UrlNormalizer::isValid($profileUrl))
                    ? UrlNormalizer::normalize($profileUrl)
                    : null;

                if ($errors !== []) {
                    $status = ImportRowStatus::Rejected;
                    $rejectedCount++;
                } elseif (
                    ($normalizedUrl && (isset($seenUrls[$normalizedUrl]) || isset($existingUrls[$normalizedUrl])))
                    || ($externalId && ($externalId !== '') && (isset($seenExternalIds[$externalId]) || isset($existingExternalIds[$externalId])))
                ) {
                    $status = ImportRowStatus::Duplicate;
                    $errors[] = 'Duplicate profile URL or participant ID within this campaign.';
                    $duplicateCount++;
                } else {
                    $status = ImportRowStatus::Valid;
                    $validCount++;

                    if ($normalizedUrl) {
                        $seenUrls[$normalizedUrl] = true;
                    }

                    if ($externalId !== null && $externalId !== '') {
                        $seenExternalIds[$externalId] = true;
                    }
                }

                $buffer[] = [
                    'import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'raw' => json_encode($cells),
                    'name' => $name,
                    'profile_url' => $profileUrl,
                    'profile_url_normalized' => $normalizedUrl,
                    'email' => $email,
                    'phone' => $phone,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'status' => $status->value,
                    'errors' => $errors !== [] ? json_encode($errors) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($buffer) >= self::CHUNK_SIZE) {
                    DB::table('import_rows')->insert($buffer);
                    $buffer = [];

                    $import->update([
                        'progress' => min(99, (int) floor($processedDataRows / $totalDataRows * 100)),
                    ]);
                }
            }
        }

        if ($buffer !== []) {
            DB::table('import_rows')->insert($buffer);
        }

        $reader->close();

        $import->update([
            'status' => ImportStatus::Validated,
            'total_rows' => $totalRows,
            'valid_rows' => $validCount,
            'duplicate_rows' => $duplicateCount,
            'rejected_rows' => $rejectedCount,
            'progress' => 100,
        ]);
    }

    private function cell(array $cells, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $cells)) {
            return null;
        }

        $value = $cells[$index];

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    private function countDataRows(string $absolutePath, string $originalFilename): int
    {
        $reader = SpreadsheetReaderFactory::forFile($absolutePath, $originalFilename);
        $reader->open($absolutePath);

        $count = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $count++;
            }
        }

        $reader->close();

        return max(0, $count - 1);
    }
}
