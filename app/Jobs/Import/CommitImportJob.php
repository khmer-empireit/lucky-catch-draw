<?php

namespace App\Jobs\Import;

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\Audit\Auditor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommitImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public function __construct(public int $importId, public ?int $actorId = null)
    {
    }

    public function handle(Auditor $auditor): void
    {
        $import = Import::findOrFail($this->importId);
        $import->update(['status' => ImportStatus::Importing]);

        $inserted = 0;

        try {
            DB::transaction(function () use ($import, &$inserted) {
                $import->rows()
                    ->where('status', ImportRowStatus::Valid->value)
                    ->orderBy('id')
                    ->chunkById(self::CHUNK_SIZE, function ($rows) use ($import, &$inserted) {
                        $now = now();

                        $payload = $rows->map(fn ($row) => [
                            'campaign_id' => $import->campaign_id,
                            'import_id' => $import->id,
                            'name' => $row->name,
                            'profile_url' => $row->profile_url,
                            'profile_url_normalized' => $row->profile_url_normalized,
                            'email' => $row->email,
                            'phone' => $row->phone,
                            'external_id' => $row->external_id,
                            'eligible' => true,
                            'is_winner' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all();

                        DB::table('participants')->insert($payload);
                        $inserted += count($payload);
                    });
            });
        } catch (Throwable $e) {
            $import->update(['status' => ImportStatus::Failed]);
            $auditor->log('import.failed', $import, ['error' => $e->getMessage()]);

            throw $e;
        }

        $import->update(['status' => ImportStatus::Imported, 'progress' => 100]);

        $auditor->log('import.committed', $import, [
            'campaign_id' => $import->campaign_id,
            'participants_inserted' => $inserted,
        ]);
    }
}
