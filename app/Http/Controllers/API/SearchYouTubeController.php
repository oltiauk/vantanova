<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\YouTubeSearchRequest;
use App\Models\Song;
use App\Services\YouTubeService;
use Illuminate\Http\Request;

class SearchYouTubeController extends Controller
{
    public function __invoke(YouTubeSearchRequest $request, Song $song, YouTubeService $youTubeService)
    {
        return response()->json($youTubeService->searchVideosRelatedToSong($song, (string) $request->pageToken));
    }

    public function searchByQuery(Request $request, YouTubeService $youTubeService)
    {
        $query = $request->input('q');
        $pageToken = $request->input('pageToken', '') ?? '';
        
        if (!$query) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        return response()->json($youTubeService->searchVideosByQuery($query, $pageToken));
    }
}
