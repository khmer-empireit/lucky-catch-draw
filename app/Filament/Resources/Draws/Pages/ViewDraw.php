<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Resources\Draws\DrawResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDraw extends ViewRecord
{
    protected static string $resource = DrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
