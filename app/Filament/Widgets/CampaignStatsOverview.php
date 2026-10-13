<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Draw;
use App\Models\Participant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Campaigns', Campaign::count())
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray'),

            Stat::make('Active Campaigns', Campaign::whereIn('status', [
                CampaignStatus::Ready,
                CampaignStatus::InProgress,
            ])->count())
                ->icon('heroicon-o-play-circle')
                ->color('warning'),

            Stat::make('Completed Campaigns', Campaign::where('status', CampaignStatus::Completed)->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Total Participants', Participant::count())
                ->icon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('Total Winners', Draw::where('status', 'valid')->count())
                ->icon('heroicon-o-trophy')
                ->color('primary'),
        ];
    }
}
