<?php

namespace App\Filament\Resources\Draws\Tables;

use App\Enums\DrawStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Draw;
use App\Services\Excel\WinnersExporter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('drawn_at', 'desc')
            ->columns([
                TextColumn::make('participant.name')
                    ->label('Winner')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('campaign.prize_name')
                    ->label('Prize')
                    ->toggleable(),
                TextColumn::make('sequence')->label('#')->alignCenter(),
                TextColumn::make('drawn_at')->dateTime('M j, Y g:ia')->sortable(),
                TextColumn::make('drawnBy.name')->label('Drawn by')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DrawStatus $state) => $state->label())
                    ->color(fn (DrawStatus $state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('campaign')
                    ->relationship('campaign', 'name')
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(collect(DrawStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => WinnersExporter::download(Draw::query())),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('openProfile')
                    ->label('Profile')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Draw $record) => $record->participant?->profile_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Draw $record) => filled($record->participant?->profile_url)),
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Draw $record) => route('draws.print', $record))
                    ->openUrlInNewTab(),
                Action::make('viewAudit')
                    ->label('Audit')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->url(fn (Draw $record) => CampaignResource::getUrl('view', ['record' => $record->campaign_id])),
            ]);
    }
}
