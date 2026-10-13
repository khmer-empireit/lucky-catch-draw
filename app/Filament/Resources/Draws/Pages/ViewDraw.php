<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Enums\DrawStatus;
use App\Filament\Resources\Draws\DrawResource;
use App\Models\Draw;
use App\Services\Draw\DrawService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDraw extends ViewRecord
{
    protected static string $resource = DrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print winner report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (Draw $record) => route('draws.print', $record))
                ->openUrlInNewTab(),

            Action::make('void')
                ->label('Void & approve redraw')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')->label('Reason for voiding')->required()->rows(3),
                ])
                ->visible(fn (Draw $record) => auth()->user()->can('void', $record) && $record->status === DrawStatus::Valid)
                ->action(function (array $data, Draw $record) {
                    app(DrawService::class)->void($record, auth()->user(), $data['reason']);

                    Notification::make()->title('Draw voided — slot is open for a redraw')->success()->send();
                }),
        ];
    }
}
