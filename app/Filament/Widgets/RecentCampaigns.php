<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentCampaigns extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Campaigns')
            ->query(Campaign::query()->latest())
            ->paginated(false)
            ->recordUrl(fn (Campaign $record) => CampaignResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('winners_to_select')
                    ->label('Winners')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ]);
    }
}
