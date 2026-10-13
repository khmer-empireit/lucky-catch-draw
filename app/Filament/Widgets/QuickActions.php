<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions';

    public function getViewData(): array
    {
        $latestDraftOrReady = Campaign::whereIn('status', ['draft', 'ready'])->latest()->first();

        return [
            'createUrl' => CampaignResource::getUrl('create'),
            'uploadUrl' => $latestDraftOrReady
                ? CampaignResource::getUrl('view', ['record' => $latestDraftOrReady])
                : CampaignResource::getUrl('index'),
            'drawUrl' => CampaignResource::getUrl('index'),
        ];
    }
}
