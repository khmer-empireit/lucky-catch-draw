<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        TextEntry::make('name')
                            ->label('Campaign')
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                            ->color(fn (CampaignStatus $state) => $state->color()),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description.'),
                    ]),

                Grid::make(3)
                    ->components([
                        Section::make('Prize')
                            ->components([
                                ImageEntry::make('prize_image')
                                    ->disk('public')
                                    ->label('')
                                    ->visible(fn (Campaign $record) => filled($record->prize_image)),
                                TextEntry::make('prize_name')
                                    ->label('')
                                    ->placeholder('No prize set')
                                    ->size('lg')
                                    ->weight('semibold'),
                            ]),

                        Section::make('Participants')
                            ->components([
                                TextEntry::make('participants_count')
                                    ->label('Total')
                                    ->state(fn (Campaign $record) => $record->participants()->count()),
                                TextEntry::make('eligible_count')
                                    ->label('Eligible')
                                    ->state(fn (Campaign $record) => $record->eligibleParticipants()->count()),
                                TextEntry::make('participants_locked_at')
                                    ->label('List locked')
                                    ->dateTime()
                                    ->placeholder('Not locked yet'),
                            ]),

                        Section::make('Draw progress')
                            ->components([
                                TextEntry::make('winners_to_select')
                                    ->label('Winners to select'),
                                TextEntry::make('winners_drawn')
                                    ->label('Winners drawn')
                                    ->state(fn (Campaign $record) => $record->validDraws()->count()),
                                TextEntry::make('allow_repeat_winners')
                                    ->label('Repeat winners')
                                    ->formatStateUsing(fn (bool $state) => $state ? 'Allowed' : 'Not allowed'),
                            ]),
                    ]),
            ]);
    }
}
