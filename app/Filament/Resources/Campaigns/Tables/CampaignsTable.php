<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Enums\CampaignStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->disk('public')
                    ->circular()
                    ->label(''),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => $state->color()),
                TextColumn::make('prize_name')
                    ->label('Prize')
                    ->toggleable(),
                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
                    ->alignCenter(),
                TextColumn::make('winners_to_select')
                    ->label('Winners')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
