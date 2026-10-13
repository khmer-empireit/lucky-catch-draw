<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\AuditLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Audit';

    /**
     * auditLogs() is a hand-rolled query (not a real Eloquent relation, since
     * an audit entry's auditable can be the campaign or any of its imports,
     * participants, or draws). Filament's default record-based authorization
     * check assumes a true Relation here, so it's re-implemented directly.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', AuditLog::class);
    }

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
