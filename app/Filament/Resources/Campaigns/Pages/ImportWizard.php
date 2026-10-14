<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Jobs\Import\CommitImportJob;
use App\Jobs\Import\ValidateImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Excel\ImportErrorReportExporter;
use App\Services\Import\ColumnMapper;
use App\Services\Import\FileInspector;
use App\Services\Import\SpreadsheetReaderFactory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportWizard extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CampaignResource::class;

    protected string $view = 'filament.resources.campaigns.pages.import-wizard';

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, string> */
    public array $headers = [];

    public ?int $importId = null;

    public string $search = '';

    public ?string $statusFilterValue = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()?->can('create', Import::class), 403);

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Upload participants — '.$this->getRecord()->name;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('file')
                    ->label('Spreadsheet file (.xlsx or .csv, max 10MB)')
                    ->disk('local')
                    ->directory('imports/uploads')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                        'application/zip',
                    ])
                    ->maxSize(10240)
                    ->required()
                    ->visible(fn () => $this->step === 1)
                    ->afterStateUpdated(function ($state) {
                        if ($state instanceof TemporaryUploadedFile) {
                            $this->data['original_name'] = $state->getClientOriginalName();
                        }
                    })
                    ->helperText('Headers expected: Participant Name, Profile URL, Email, Phone Number, Participant ID.'),

                Select::make('map_name')
                    ->label('Participant Name column')
                    ->options(fn () => $this->headerOptions())
                    ->required()
                    ->native(false)
                    ->visible(fn () => $this->step === 2),
                Select::make('map_profile_url')
                    ->label('Profile URL column')
                    ->options(fn () => $this->headerOptions())
                    ->required()
                    ->native(false)
                    ->visible(fn () => $this->step === 2),
                Select::make('map_email')
                    ->label('Email column (optional)')
                    ->options(fn () => $this->headerOptions())
                    ->native(false)
                    ->visible(fn () => $this->step === 2),
                Select::make('map_phone')
                    ->label('Phone Number column (optional)')
                    ->options(fn () => $this->headerOptions())
                    ->native(false)
                    ->visible(fn () => $this->step === 2),
                Select::make('map_external_id')
                    ->label('Participant ID column (optional)')
                    ->options(fn () => $this->headerOptions())
                    ->native(false)
                    ->visible(fn () => $this->step === 2),
            ]);
    }

    /** @return array<int, string> */
    private function headerOptions(): array
    {
        return collect($this->headers)
            ->mapWithKeys(fn ($header, $index) => [$index => (string) $header])
            ->all();
    }

    public function getImport(): ?Import
    {
        return $this->importId ? Import::find($this->importId) : null;
    }

    public function goToMapping(): void
    {
        $state = $this->form->getState();
        $relativePath = $state['file'] ?? null;

        if (! $relativePath) {
            Notification::make()->title('Please choose a file first.')->danger()->send();

            return;
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $originalName = $this->data['original_name'] ?? basename($relativePath);
        $extension = FileInspector::extension($originalName);

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            $this->rejectUpload($relativePath, 'Only .xlsx or .csv files are supported.');

            return;
        }

        if ($extension === 'xlsx' && ! FileInspector::looksLikeXlsx($absolutePath)) {
            $this->rejectUpload($relativePath, 'This file does not look like a valid Excel (.xlsx) file.');

            return;
        }

        // Re-uploading the exact same file into the same campaign is allowed:
        // any rows that were already imported get caught again downstream by
        // ValidateImportJob's duplicate-against-existing-participants check,
        // so nothing can actually double-import — this is just not blocked
        // at the file level anymore.
        $sha256 = FileInspector::sha256($absolutePath);

        $reader = SpreadsheetReaderFactory::forFile($absolutePath, $originalName);
        $reader->open($absolutePath);
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $headers = $row->toArray();
                break 2;
            }
        }

        $reader->close();

        if ($headers === []) {
            $this->rejectUpload($relativePath, 'The file appears to be empty.');

            return;
        }

        $this->headers = array_map(fn ($h) => (string) $h, $headers);
        $this->data['original_name'] = $originalName;
        $this->data['sha256'] = $sha256;
        // The FileUpload field becomes invisible from step 2 onward, and
        // Filament excludes hidden components from getState() — so the
        // stored path is stashed under a plain (non-component) data key.
        $this->data['stored_path'] = $relativePath;

        $guess = ColumnMapper::guess($this->headers);
        $this->data['map_name'] = $guess['name'] ?? null;
        $this->data['map_profile_url'] = $guess['profile_url'] ?? null;
        $this->data['map_email'] = $guess['email'] ?? null;
        $this->data['map_phone'] = $guess['phone'] ?? null;
        $this->data['map_external_id'] = $guess['external_id'] ?? null;

        $this->step = 2;
    }

    private function rejectUpload(string $relativePath, string $message): void
    {
        Storage::disk('local')->delete($relativePath);
        $this->data['file'] = null;
        Notification::make()->title($message)->danger()->send();
    }

    public function startValidation(): void
    {
        $state = $this->form->getState();

        if (blank($state['map_name'] ?? null) || blank($state['map_profile_url'] ?? null)) {
            Notification::make()->title('Name and Profile URL columns are required.')->danger()->send();

            return;
        }

        $columnMap = array_filter([
            'name' => $state['map_name'] !== null ? (int) $state['map_name'] : null,
            'profile_url' => $state['map_profile_url'] !== null ? (int) $state['map_profile_url'] : null,
            'email' => $state['map_email'] !== null ? (int) $state['map_email'] : null,
            'phone' => $state['map_phone'] !== null ? (int) $state['map_phone'] : null,
            'external_id' => $state['map_external_id'] !== null ? (int) $state['map_external_id'] : null,
        ], fn ($v) => $v !== null);

        $import = Import::create([
            'campaign_id' => $this->getRecord()->id,
            'original_file_name' => $this->data['original_name'],
            'stored_path' => $this->data['stored_path'],
            'file_sha256' => $this->data['sha256'],
            'status' => ImportStatus::Mapped,
            'column_map' => $columnMap,
            'created_by' => auth()->id(),
        ]);

        $this->importId = $import->id;

        ValidateImportJob::dispatch($import->id);

        $this->step = 3;
    }

    public function refreshValidationProgress(): void
    {
        // Livewire re-renders on every poll tick; nothing to do here beyond
        // letting getImport() re-fetch fresh state from the database.
    }

    public function goToReview(): void
    {
        $this->step = 4;
    }

    /** @return LengthAwarePaginator<int, ImportRow> */
    public function getReviewRowsProperty(): LengthAwarePaginator
    {
        $query = ImportRow::query()->where('import_id', $this->importId);

        if ($this->statusFilterValue) {
            $query->where('status', $this->statusFilterValue);
        }

        if ($this->search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('profile_url', 'like', "%{$this->search}%"));
        }

        return $query->orderBy('row_number')->paginate(20);
    }

    public function excludeRow(int $rowId): void
    {
        $row = ImportRow::where('import_id', $this->importId)->findOrFail($rowId);

        if ($row->status !== ImportRowStatus::Valid) {
            return;
        }

        $import = $this->getImport();
        $row->update(['status' => ImportRowStatus::Rejected, 'errors' => ['Manually excluded by reviewer.']]);
        $import?->decrement('valid_rows');
        $import?->increment('rejected_rows');
    }

    public function downloadErrorReport()
    {
        return ImportErrorReportExporter::download($this->getImport());
    }

    public function confirmImport(): void
    {
        $import = $this->getImport();

        if (! $import || $import->valid_rows < 1) {
            Notification::make()->title('There are no valid rows to import.')->danger()->send();

            return;
        }

        CommitImportJob::dispatch($import->id, auth()->id());

        $this->step = 5;
    }

    public function viewParticipants(): void
    {
        $this->redirect(CampaignResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
