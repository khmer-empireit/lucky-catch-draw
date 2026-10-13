<x-filament-panels::page>
    @php
        $steps = [
            1 => 'Upload',
            2 => 'Map columns',
            3 => 'Validate',
            4 => 'Review',
            5 => 'Import',
        ];
        $import = $this->getImport();
    @endphp

    <div class="flex items-center gap-2 overflow-x-auto pb-2">
        @foreach ($steps as $number => $label)
            <div class="flex items-center gap-2">
                <span @class([
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-primary-600 text-white' => $this->step === $number,
                    'bg-success-600 text-white' => $this->step > $number,
                    'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $this->step < $number,
                ])>
                    @if ($this->step > $number) ✓ @else {{ $number }} @endif
                </span>
                <span @class(['text-sm font-medium', 'text-gray-500' => $this->step !== $number])>{{ $label }}</span>
            </div>
            @if (! $loop->last)
                <div class="h-px w-8 shrink-0 bg-gray-300 dark:bg-gray-600"></div>
            @endif
        @endforeach
    </div>

    {{-- Step 1: Upload --}}
    @if ($this->step === 1)
        <x-filament::section heading="Upload a spreadsheet">
            <div class="mb-4">
                <a href="{{ route('imports.template') }}" class="text-sm font-medium text-primary-600 hover:underline">
                    Download the .xlsx template
                </a>
            </div>

            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button wire:click="goToMapping" wire:loading.attr="disabled">
                    Next: Map columns
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{-- Step 2: Map columns --}}
    @if ($this->step === 2)
        <x-filament::section heading="Map your columns">
            {{ $this->form }}

            <div class="mt-6 flex justify-between">
                <x-filament::button color="gray" wire:click="$set('step', 1)">
                    Back
                </x-filament::button>
                <x-filament::button wire:click="startValidation" wire:loading.attr="disabled">
                    Next: Validate
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{-- Step 3: Validate --}}
    @if ($this->step === 3)
        <div wire:poll.2s="refreshValidationProgress">
            <x-filament::section heading="Validating your file">
                @if ($import)
                    <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full gold-gradient-bg rounded-full transition-all" style="width: {{ $import->progress }}%; background-image: linear-gradient(90deg, #f2b33d, #ff6b2c);"></div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">{{ $import->progress }}% — status: {{ $import->status->label() }}</p>

                    @if ($import->status->value === 'validated')
                        <div class="mt-6 flex justify-end">
                            <x-filament::button wire:click="goToReview">
                                Continue to review
                            </x-filament::button>
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </div>
    @endif

    {{-- Step 4: Review --}}
    @if ($this->step === 4 && $import)
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-filament::section>
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold">{{ $import->total_rows }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500">Valid</p>
                <p class="text-2xl font-bold text-success-600">{{ $import->valid_rows }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500">Duplicates</p>
                <p class="text-2xl font-bold text-warning-600">{{ $import->duplicate_rows }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500">Rejected</p>
                <p class="text-2xl font-bold text-danger-600">{{ $import->rejected_rows }}</p>
            </x-filament::section>
        </div>

        <x-filament::section heading="Row details" class="mt-4">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-filament::input.wrapper class="max-w-xs">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Search name or URL…" />
                </x-filament::input.wrapper>

                <select wire:model.live="statusFilterValue" class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">All statuses</option>
                    <option value="valid">Valid</option>
                    <option value="duplicate">Duplicate</option>
                    <option value="rejected">Rejected</option>
                </select>

                @if ($import->duplicate_rows + $import->rejected_rows > 0)
                    <x-filament::button color="gray" wire:click="downloadErrorReport">
                        Download error report
                    </x-filament::button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="py-2">Row</th>
                            <th class="py-2">Name</th>
                            <th class="py-2">Profile URL</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Message</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->reviewRows as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2">{{ $row->row_number }}</td>
                                <td class="py-2">{{ $row->name }}</td>
                                <td class="py-2 max-w-xs truncate">{{ $row->profile_url }}</td>
                                <td class="py-2">
                                    <x-filament::badge :color="$row->status->color()">{{ $row->status->label() }}</x-filament::badge>
                                </td>
                                <td class="py-2 text-gray-500">{{ implode('; ', $row->errors ?? []) }}</td>
                                <td class="py-2">
                                    @if ($row->status->value === 'valid')
                                        <button type="button" wire:click="excludeRow({{ $row->id }})" class="text-xs text-danger-600 hover:underline">
                                            Exclude
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $this->reviewRows->links() }}
                </div>
            </div>
        </x-filament::section>

        <div class="mt-6 flex justify-end">
            <x-filament::button color="success" wire:click="confirmImport" wire:loading.attr="disabled">
                Confirm &amp; Import {{ $import->valid_rows }} Participants
            </x-filament::button>
        </div>
    @endif

    {{-- Step 5: Import --}}
    @if ($this->step === 5)
        <div wire:poll.2s="refreshValidationProgress">
            <x-filament::section heading="Importing">
                @if ($import && $import->status->value === 'imported')
                    <div class="flex flex-col items-center gap-4 py-8 text-center">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-12 w-12 text-success-600" />
                        <p class="text-lg font-semibold">{{ $import->valid_rows }} participants imported successfully.</p>
                        <x-filament::button wire:click="viewParticipants">
                            View participants
                        </x-filament::button>
                    </div>
                @elseif ($import && $import->status->value === 'failed')
                    <p class="text-danger-600">The import failed and no participants were added. Please try again.</p>
                @else
                    <p class="text-sm text-gray-500">Importing participants…</p>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
