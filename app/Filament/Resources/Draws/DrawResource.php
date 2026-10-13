<?php

namespace App\Filament\Resources\Draws;

use App\Filament\Resources\Draws\Pages\ListDraws;
use App\Filament\Resources\Draws\Pages\ViewDraw;
use App\Filament\Resources\Draws\Schemas\DrawInfolist;
use App\Filament\Resources\Draws\Tables\DrawsTable;
use App\Models\Draw;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DrawResource extends Resource
{
    protected static ?string $model = Draw::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function infolist(Schema $schema): Schema
    {
        return DrawInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDraws::route('/'),
            'view' => ViewDraw::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
