<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Draws\DrawResource;
use App\Models\Draw;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentDraws extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Draw Results')
            ->query(Draw::query()->latest('drawn_at')->with(['participant', 'campaign']))
            ->paginated(false)
            ->recordUrl(fn (Draw $record) => DrawResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('participant.name')
                    ->label('Winner')
                    ->weight('semibold'),
                TextColumn::make('campaign.name')
                    ->label('Campaign'),
                TextColumn::make('sequence')
                    ->label('Seq')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('drawn_at')
                    ->label('Drawn')
                    ->dateTime('M j, g:ia')
                    ->sortable(),
            ]);
    }
}
