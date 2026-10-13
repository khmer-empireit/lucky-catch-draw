<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Draw;
use App\Services\Draw\DrawService;
use App\Services\Draw\Exceptions\DrawNotAllowedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DrawController extends Controller
{
    public function __construct(private readonly DrawService $drawService)
    {
    }

    public function show(Campaign $campaign): InertiaResponse
    {
        $this->authorize('viewDrawScreen', $campaign);

        $eligibleQuery = $campaign->eligibleParticipants();

        if (! $campaign->allow_repeat_winners) {
            $eligibleQuery->where('is_winner', false);
        }

        $eligibleCount = (clone $eligibleQuery)->count();

        $sample = (clone $eligibleQuery)
            ->inRandomOrder()
            ->limit(200)
            ->get(['id', 'name', 'profile_url'])
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->name,
                'profile_url' => $participant->profile_url,
            ]);

        return Inertia::render('Draw/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'prize_name' => $campaign->prize_name,
                'prize_image' => $campaign->prize_image ? Storage::disk('public')->url($campaign->prize_image) : null,
                'winners_to_select' => $campaign->winners_to_select,
                'status' => $campaign->status->value,
            ],
            'eligibleCount' => $eligibleCount,
            'winnersDrawn' => $campaign->validDraws()->count(),
            'winnersRemaining' => $campaign->winnersRemaining(),
            'canDraw' => $campaign->winnersRemaining() > 0 && auth()->user()->can('draw', $campaign),
            'sample' => $sample,
        ]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('draw', $campaign);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        try {
            $draw = $this->drawService->draw($campaign, $request->user(), $validated['idempotency_key']);
        } catch (DrawNotAllowedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $draw->loadMissing('participant');
        $campaign->refresh();

        return response()->json($this->drawPayload($draw, $campaign));
    }

    public function latest(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewDrawScreen', $campaign);

        $draw = $campaign->validDraws()->latest('drawn_at')->with('participant')->first();

        if (! $draw) {
            return response()->json(['draw' => null]);
        }

        return response()->json(['draw' => $this->drawPayload($draw, $campaign)]);
    }

    public function print(Draw $draw): InertiaResponse
    {
        $this->authorize('print', $draw);

        $draw->loadMissing(['participant', 'campaign', 'drawnBy']);

        return Inertia::render('Draws/Print', [
            'draw' => [
                'id' => $draw->id,
                'sequence' => $draw->sequence,
                'status' => $draw->status->value,
                'drawn_at' => $draw->drawn_at->toIso8601String(),
                'pool_size' => $draw->pool_size,
                'pool_hash' => $draw->pool_hash,
                'winner' => [
                    'name' => $draw->participant?->name,
                    'profile_url' => $draw->participant?->profile_url,
                ],
                'drawn_by' => $draw->drawnBy?->name,
                'campaign' => [
                    'name' => $draw->campaign->name,
                    'prize_name' => $draw->campaign->prize_name,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function drawPayload(Draw $draw, Campaign $campaign): array
    {
        return [
            'draw_id' => $draw->id,
            'sequence' => $draw->sequence,
            'winner' => [
                'id' => $draw->participant->id,
                'name' => $draw->participant->name,
                'profile_url' => $draw->participant->profile_url,
            ],
            'pool_size' => $draw->pool_size,
            'pool_hash' => Str::limit($draw->pool_hash, 12, ''),
            'winners_remaining' => $campaign->winnersRemaining(),
        ];
    }
}
