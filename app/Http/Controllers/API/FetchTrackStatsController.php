<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LastfmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FetchTrackStatsController extends Controller
{
    public function __construct(private readonly LastfmService $lastfmService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        Log::info('🎵 LastFM track stats request received', [
            'tracks_count' => count($request->input('tracks', [])),
            'sample_tracks' => array_slice($request->input('tracks', []), 0, 3)
        ]);

        $request->validate([
            'tracks' => 'required|array|max:20',
            'tracks.*.artist' => 'required|string',
            'tracks.*.track' => 'required|string',
        ]);

        $tracks = $request->input('tracks');
        
        try {
            Log::info('🎵 Calling LastFM batch service', ['tracks_count' => count($tracks)]);
            $stats = $this->lastfmService->batchGetTrackInformation($tracks);
            
            Log::info('🎵 LastFM batch service response', [
                'stats_count' => count($stats),
                'sample_stats' => array_slice($stats, 0, 2, true)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('🎵 Failed to fetch LastFM track stats', [
                'error' => $e->getMessage(),
                'tracks_count' => count($tracks),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch track statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}