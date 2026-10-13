<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\Participant;
use App\Services\Audit\Auditor;
use App\Services\Excel\ParticipantsExporter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('eligibility_note')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('profile_url')
                    ->label('Profile')
                    ->formatStateUsing(fn (string $state) => str($state)->after('://')->limit(40))
                    ->url(fn (Participant $record) => $record->profile_url)
                    ->openUrlInNewTab()
                    ->color('primary'),
                TextColumn::make('email')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->toggleable(),
                TextColumn::make('import.original_file_name')
                    ->label('Import')
                    ->toggleable()
                    ->placeholder('—'),
                ToggleColumn::make('eligible')
                    ->disabled(fn (Participant $record) => ! Gate::allows('update', $record))
                    ->afterStateUpdated(function (Participant $record, bool $state) {
                        app(Auditor::class)->log('participant.eligibility_changed', $record, [
                            'eligible' => $state,
                            'campaign_id' => $record->campaign_id,
                        ]);
                    }),
                IconColumn::make('is_winner')
                    ->label('Winner')
                    ->boolean()
                    ->trueIcon('heroicon-o-trophy')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),
            ])
            ->filters([
                TernaryFilter::make('eligible')
                    ->label('Eligibility')
                    ->placeholder('All participants')
                    ->trueLabel('Eligible only')
                    ->falseLabel('Ineligible only'),
                TernaryFilter::make('is_winner')
                    ->label('Winners')
                    ->placeholder('All participants')
                    ->trueLabel('Winners only')
                    ->falseLabel('Non-winners only'),
                SelectFilter::make('import_id')
                    ->label('Import')
                    ->relationship('import', 'original_file_name'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => ParticipantsExporter::download($this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Participant $record) => Gate::allows('update', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markEligible')
                        ->label('Mark eligible')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->schema([
                            Textarea::make('note')->label('Note (optional)'),
                        ])
                        ->action(function (EloquentCollection $records, array $data) {
                            foreach ($records as $participant) {
                                if (! Gate::allows('update', $participant)) {
                                    continue;
                                }

                                $participant->update([
                                    'eligible' => true,
                                    'eligibility_note' => $data['note'] ?: null,
                                ]);

                                app(Auditor::class)->log('participant.eligibility_changed', $participant, [
                                    'eligible' => true,
                                    'note' => $data['note'] ?? null,
                                    'campaign_id' => $participant->campaign_id,
                                ]);
                            }

                            Notification::make()->title('Selected participants marked eligible')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('markIneligible')
                        ->label('Mark ineligible')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Textarea::make('note')->label('Reason')->required(),
                        ])
                        ->action(function (EloquentCollection $records, array $data) {
                            foreach ($records as $participant) {
                                if (! Gate::allows('update', $participant)) {
                                    continue;
                                }

                                $participant->update([
                                    'eligible' => false,
                                    'eligibility_note' => $data['note'],
                                ]);

                                app(Auditor::class)->log('participant.eligibility_changed', $participant, [
                                    'eligible' => false,
                                    'note' => $data['note'],
                                    'campaign_id' => $participant->campaign_id,
                                ]);
                            }

                            Notification::make()->title('Selected participants marked ineligible')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
