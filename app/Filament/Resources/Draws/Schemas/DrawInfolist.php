<?php

namespace App\Filament\Resources\Draws\Schemas;

use App\Enums\DrawStatus;
use App\Models\Draw;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DrawInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->components([
                        TextEntry::make('participant.name')
                            ->label('Winner')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('participant.profile_url')
                            ->label('Profile')
                            ->url(fn (Draw $record) => $record->participant?->profile_url)
                            ->openUrlInNewTab(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (DrawStatus $state) => $state->label())
                            ->color(fn (DrawStatus $state) => $state->color()),

                        TextEntry::make('campaign.name')->label('Campaign'),
                        TextEntry::make('campaign.prize_name')->label('Prize')->placeholder('—'),
                        TextEntry::make('sequence')->label('Sequence'),

                        TextEntry::make('drawn_at')->label('Drawn at')->dateTime(),
                        TextEntry::make('drawnBy.name')->label('Drawn by'),
                        TextEntry::make('pool_size')->label('Pool size'),

                        TextEntry::make('pool_hash')->label('Pool hash')->copyable(),
                        TextEntry::make('idempotency_key')->label('Idempotency key')->copyable(),
                    ]),

                Section::make('Void details')
                    ->visible(fn (Draw $record) => $record->status === DrawStatus::Voided)
                    ->columns(3)
                    ->components([
                        TextEntry::make('voided_reason')->label('Reason')->columnSpanFull(),
                        TextEntry::make('voidedBy.name')->label('Voided by'),
                        TextEntry::make('voided_at')->label('Voided at')->dateTime(),
                    ]),
            ]);
    }
}
