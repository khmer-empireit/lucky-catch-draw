<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\DrawStatus;
use App\Models\Draw;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DrawsRelationManager extends RelationManager
{
    protected static string $relationship = 'draws';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sequence')
            ->defaultSort('sequence')
            ->columns([
                TextColumn::make('sequence')->label('#')->alignCenter(),
                TextColumn::make('participant.name')->label('Winner')->weight('semibold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DrawStatus $state) => $state->label())
                    ->color(fn (DrawStatus $state) => $state->color()),
                TextColumn::make('drawn_at')->dateTime('M j, g:ia')->label('Drawn at'),
                TextColumn::make('drawnBy.name')->label('Drawn by')->toggleable(),
                TextColumn::make('voided_reason')->label('Void reason')->toggleable()->placeholder('—'),
                TextColumn::make('pool_hash')
                    ->label('Pool hash')
                    ->formatStateUsing(fn (string $state) => substr($state, 0, 10).'…')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('openProfile')
                    ->label('Open profile')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Draw $record) => $record->participant?->profile_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Draw $record) => filled($record->participant?->profile_url)),
                Action::make('print')
                    ->label('Print report')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Draw $record) => route('draws.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
