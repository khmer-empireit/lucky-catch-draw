<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('details')
                    ->label('Details')
                    // ->formatStateUsing() would receive each array value one at a
                    // time here (Filament treats an array column state as a list
                    // of badges/tags) — ->getStateUsing() replaces state
                    // resolution entirely so the whole array is encoded as one
                    // string before Filament ever iterates it.
                    ->getStateUsing(fn (AuditLog $record): string => $record->details ? json_encode($record->details) : '—')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::query()->distinct()->pluck('action', 'action')->all()),
                SelectFilter::make('actor_id')
                    ->label('Actor')
                    ->relationship('actor', 'name')
                    ->searchable(),
                SelectFilter::make('campaign')
                    ->label('Campaign')
                    ->options(fn () => Campaign::query()->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $campaignId = (int) $data['value'];

                        return $query->where(function (Builder $q) use ($campaignId) {
                            $q->where(function (Builder $direct) use ($campaignId) {
                                $direct->where('auditable_type', Campaign::class)->where('auditable_id', $campaignId);
                            })->orWhere('details->campaign_id', $campaignId);
                        });
                    }),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
