<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\Excel\ImportErrorReportExporter;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImportsRelationManager extends RelationManager
{
    protected static string $relationship = 'imports';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_file_name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_file_name')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ImportStatus $state) => $state->label())
                    ->color(fn (ImportStatus $state) => $state->color()),
                TextColumn::make('progress')
                    ->suffix('%'),
                TextColumn::make('total_rows')->label('Total'),
                TextColumn::make('valid_rows')->label('Valid')->color('success'),
                TextColumn::make('duplicate_rows')->label('Dup.')->color('warning'),
                TextColumn::make('rejected_rows')->label('Rejected')->color('danger'),
                TextColumn::make('creator.name')->label('Uploaded by')->toggleable(),
                TextColumn::make('created_at')->dateTime('M j, g:ia')->toggleable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('downloadErrors')
                    ->label('Error report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Import $record) => $record->duplicate_rows + $record->rejected_rows > 0)
                    ->action(fn (Import $record) => ImportErrorReportExporter::download($record)),
            ])
            ->toolbarActions([]);
    }
}
