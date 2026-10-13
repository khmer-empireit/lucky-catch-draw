<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Audit';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('action')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextColumn::make('details')
                    ->label('Details')
                    ->formatStateUsing(fn (?array $state) => $state ? json_encode($state) : '—')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('ip')->label('IP')->toggleable(),
                TextColumn::make('created_at')->dateTime('M j, g:ia')->label('When'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
