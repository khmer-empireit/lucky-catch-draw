<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\AuditLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            // auditLogs() returns a plain Eloquent Builder, not a real
            // Relation. Filament's relationship-based query resolution
            // (Table::getRelationshipQuery()) unconditionally calls
            // ->getQuery() on whatever getRelationship() returns and expects
            // an Eloquent Builder back; a Relation's getQuery() does return
            // one, but a Builder's getQuery() unwraps one level further to
            // the raw query builder and breaks that contract. Setting an
            // explicit ->query() here takes priority (see Table::getQuery())
            // and bypasses that broken path entirely.
            ->query(fn (): Builder => $this->getOwnerRecord()->auditLogs())
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
                    // See AuditLogsTable::configure() for why this must be
                    // ->getStateUsing(), not ->formatStateUsing().
                    ->getStateUsing(fn (AuditLog $record): string => $record->details ? json_encode($record->details) : '—')
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
