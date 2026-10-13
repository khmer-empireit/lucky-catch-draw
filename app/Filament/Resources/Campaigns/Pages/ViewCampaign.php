<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Pages\ImportWizard as ImportWizardPage;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Import;
use App\Services\Audit\Auditor;
use App\Services\Draw\DrawService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadExcel')
                ->label('Upload Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn (Campaign $record) => ImportWizardPage::getUrl(['record' => $record]))
                ->visible(fn (Campaign $record) => auth()->user()->can('create', Import::class) && ! $record->isLocked()),

            Action::make('confirmParticipantList')
                ->label('Confirm participant list')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm the participant list?')
                ->modalDescription('This locks the list and marks the campaign Ready to draw. Eligibility can still change, but no new imports can be confirmed once a draw has started.')
                ->visible(fn (Campaign $record) => auth()->user()->can('confirmParticipantList', $record))
                ->action(function (Campaign $record) {
                    $record->update([
                        'participants_locked_at' => now(),
                        'status' => CampaignStatus::Ready,
                    ]);

                    app(Auditor::class)->log('campaign.list_confirmed', $record, [
                        'eligible_count' => $record->eligibleParticipants()->count(),
                    ]);

                    Notification::make()->title('Participant list confirmed — campaign is Ready')->success()->send();
                }),

            Action::make('openDrawScreen')
                ->label('Open Draw Screen')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->url(fn (Campaign $record) => route('draw.show', $record))
                ->openUrlInNewTab()
                ->visible(fn (Campaign $record) => auth()->user()->can('openDrawScreen', $record)),

            Action::make('voidAndRedraw')
                ->label('Void & approve redraw')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->schema([
                    Select::make('draw_id')
                        ->label('Draw to void')
                        ->options(fn (Campaign $record) => $record->validDraws()
                            ->with('participant')
                            ->get()
                            ->mapWithKeys(fn (Draw $draw) => [$draw->id => "#{$draw->sequence} — {$draw->participant?->name}"]))
                        ->required()
                        ->native(false),
                    Textarea::make('reason')
                        ->label('Reason for voiding')
                        ->required()
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Void a draw and open its slot for a redraw')
                ->visible(fn (Campaign $record) => auth()->user()->can('void', $record) && $record->validDraws()->exists())
                ->action(function (array $data, Campaign $record) {
                    $draw = Draw::findOrFail($data['draw_id']);
                    app(DrawService::class)->void($draw, auth()->user(), $data['reason']);

                    Notification::make()->title('Draw voided — slot is open for a redraw')->success()->send();
                }),

            Action::make('cancelCampaign')
                ->label('Cancel campaign')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel this campaign?')
                ->modalDescription('Cancelled campaigns cannot be drawn. This cannot be undone.')
                ->visible(fn (Campaign $record) => auth()->user()->can('cancel', $record)
                    && ! in_array($record->status, [CampaignStatus::Completed, CampaignStatus::Cancelled], true))
                ->action(function (Campaign $record) {
                    $record->update(['status' => CampaignStatus::Cancelled]);
                    app(Auditor::class)->log('campaign.cancelled', $record);
                    Notification::make()->title('Campaign cancelled')->success()->send();
                }),

            EditAction::make(),
        ];
    }
}
