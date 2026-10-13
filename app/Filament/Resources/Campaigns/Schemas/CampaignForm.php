<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignForm
{
    private const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign details')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        DateTimePicker::make('start_at')
                            ->native(false),
                        DateTimePicker::make('end_at')
                            ->native(false)
                            ->after('start_at'),
                        FileUpload::make('cover_image')
                            ->image()
                            ->disk('public')
                            ->directory('campaigns/covers')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(self::ACCEPTED_IMAGE_TYPES)
                            ->columnSpanFull(),
                    ]),

                Section::make('Prize')
                    ->columns(2)
                    ->components([
                        TextInput::make('prize_name')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        FileUpload::make('prize_image')
                            ->image()
                            ->disk('public')
                            ->directory('campaigns/prizes')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(self::ACCEPTED_IMAGE_TYPES)
                            ->columnSpanFull(),
                    ]),

                Section::make('Draw rules')
                    ->columns(2)
                    ->components([
                        TextInput::make('winners_to_select')
                            ->label('Number of winners')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Toggle::make('allow_repeat_winners')
                            ->label('Allow the same participant to win more than once')
                            ->inline(false),
                    ]),
            ]);
    }
}
