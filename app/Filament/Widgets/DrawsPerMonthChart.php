<?php

namespace App\Filament\Widgets;

use App\Models\Draw;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DrawsPerMonthChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return 'Draws per Month';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => Carbon::now()->subMonths($i)->startOfMonth());

        $counts = Draw::query()
            ->where('status', 'valid')
            ->where('drawn_at', '>=', $months->first())
            ->get()
            ->groupBy(fn (Draw $draw) => $draw->drawn_at->format('Y-m'));

        return [
            'datasets' => [
                [
                    'label' => 'Valid Draws',
                    'data' => $months->map(fn (Carbon $month) => $counts->get($month->format('Y-m'), collect())->count())->all(),
                    'backgroundColor' => '#f2a83d',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $months->map(fn (Carbon $month) => $month->format('M Y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
