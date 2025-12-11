<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\LabelSearchRequest;
use App\Services\LabelSearchService;
use App\Services\SpotifyService;
use Illuminate\Support\Facades\Log;

class LabelSearchController extends Controller
{
    public function __construct(
        private readonly LabelSearchService $labelSearchService
    ) {
    }

    public function __invoke(LabelSearchRequest $request)
    {
        if (!SpotifyService::enabled()) {
            return response()->json(['error' => 'Spotify integration is not enabled'], 503);
        }

        $label = $request->validated('label');
        $includeNew = $request->validated('new', false);
        $includeHipster = $request->validated('hipster', false);
        $releaseYear = $request->validated('release_year');
        $limit = max(1, min(100, (int) $request->validated('limit', 50)));

        try {
            $tracks = $this->labelSearchService->search($label, [
                'new' => $includeNew,
                'hipster' => $includeHipster,
                'release_year' => $releaseYear,
                'limit' => $limit,
            ], auth()->id());

            Log::info('Final label search response', [
                'label' => $label,
                'track_count' => count($tracks),
            ]);

            return response()->json(['tracks' => $tracks]);

        } catch (\Exception $e) {
            Log::error('Label search failed', [
                'label' => $label,
                'query' => $query ?? 'unknown',
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json(['error' => 'Search failed'], 500);
        }
    }
}
