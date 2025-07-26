<?php

/**
 * SoundCloud Search API Controller
 * 
 * Handles HTTP requests for SoundCloud music search and embed URL generation.
 * Provides RESTful API endpoints for frontend clients to search SoundCloud tracks
 * with advanced filtering options and generate embeddable player URLs.
 * 
 * Endpoints:
 * - GET /api/soundcloud/search - Search tracks with filters
 * - POST /api/soundcloud/embed - Generate embed URLs
 * 
 * @package App\Http\Controllers\API
 * @author Koel Development Team
 * @version 1.0.0
 */

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SoundCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSoundCloudController extends Controller
{
    public function __construct(private readonly SoundCloudService $soundCloudService)
    {
        \Log::info('🎵 SoundCloud Search Controller initialized', [
            'service_enabled' => SoundCloudService::enabled(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Search SoundCloud tracks with advanced filtering
     * 
     * Handles HTTP GET requests to search SoundCloud tracks using various filter
     * parameters. Supports text search, genre filtering, BPM ranges, duration limits,
     * date ranges, and pagination. Returns JSON response with track collection.
     * 
     * Request Parameters:
     * - q: Text search query
     * - genres: Genre filter (e.g., "Dance & EDM", "Country")
     * - tags: Tag-based search filter
     * - bpm_from/bmp_to: BPM range filtering
     * - duration_from/duration_to: Duration limits in milliseconds
     * - created_from/created_to: Date range filtering
     * - limit: Maximum results (1-50, default 20)
     * - offset: Pagination offset
     * - access: Access level filter (default: "playable,preview")
     * 
     * @param Request $request HTTP request with search parameters
     * @return JsonResponse JSON response with search results or error
     */
    public function searchTracks(Request $request): JsonResponse
    {
        \Log::info('📥 SoundCloud search request received', [
            'method' => 'GET',
            'endpoint' => '/api/soundcloud/search',
            'parameters' => $request->all(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'timestamp' => now()->toISOString()
        ]);
        
        \Log::info('🔍 DEBUG: Raw parameter check', [
            'bpm_from' => $request->input('bpm_from'),
            'bpm[from]' => $request->input('bpm[from]'),
            'bmp_to' => $request->input('bmp_to'),
            'bmp[to]' => $request->input('bmp[to]'),
            'all_params' => $request->all()
        ]);

        if (!SoundCloudService::enabled()) {
            \Log::warning('❌ SoundCloud search rejected - service not enabled');
            return response()->json(['error' => 'SoundCloud integration is not enabled'], 501);
        }

        // Build search parameters from request
        $params = [];

        // Text search query
        if ($query = $request->input('q')) {
            $params['q'] = trim($query);
            \Log::info('🔍 Text search query added', ['query' => $params['q']]);
        }

        // Genre filtering using official SoundCloud genres parameter
        if ($genres = $request->input('genres')) {
            $params['genres'] = $genres;
            \Log::info('🎵 Genre filter added', ['genres' => $genres]);
        }
        
        // Also check for 'genre' parameter (used by frontend)
        if ($genre = $request->input('genre')) {
            $params['genres'] = $genre;
            \Log::info('🎵 Genre filter added (from genre param)', ['genre' => $genre]);
        }

        // Tags filtering for more precise matching
        if ($tags = $request->input('tags')) {
            $params['tags'] = $tags;
            \Log::info('🏷️ Tags filter added', ['tags' => $tags]);
        }

        // BPM filtering for tempo-based searches
        if ($bpmFrom = $request->input('bpm_from') ?: $request->input('bpm[from]') ?: $request->input('bpm.from')) {
            $params['bpm[from]'] = (int) $bpmFrom;
            \Log::info('🥁 BPM from filter added', ['bpm_from' => $params['bpm[from]']]);
        }

        if ($bpmTo = $request->input('bpm_to') ?: $request->input('bpm[to]') ?: $request->input('bpm.to')) {
            $params['bpm[to]'] = (int) $bpmTo;
            \Log::info('🥁 BPM to filter added', ['bmp_to' => $params['bpm[to]']]);
        }

        // Duration filtering (in milliseconds)
        if ($durationFrom = $request->input('duration_from')) {
            $params['duration[from]'] = (int) $durationFrom;
            \Log::info('⏱️ Duration from filter added', ['duration_from' => $params['duration[from]']]);
        }

        if ($durationTo = $request->input('duration_to')) {
            $params['duration[to]'] = (int) $durationTo;
            \Log::info('⏱️ Duration to filter added', ['duration_to' => $params['duration[to]']]);
        }

        // Date filtering for recency-based searches (handle multiple parameter formats)
        if ($createdFrom = $request->input('created_from') ?: $request->input('created_at.from') ?: $request->input('created_at[from]')) {
            $params['created_at[from]'] = $createdFrom;
            \Log::info('📅 Created from filter added', ['created_from' => $createdFrom]);
        }

        if ($createdTo = $request->input('created_to') ?: $request->input('created_at.to') ?: $request->input('created_at[to]')) {
            $params['created_at[to]'] = $createdTo;
            \Log::info('📅 Created to filter added', ['created_to' => $createdTo]);
        }

        // Pagination and result limits
        $params['limit'] = min((int) $request->input('limit', 20), 50);
        
        if ($offset = $request->input('offset')) {
            $params['offset'] = (int) $offset;
            \Log::info('📄 Pagination offset added', ['offset' => $params['offset']]);
        }

        // Access level filtering (only playable tracks)
        $params['access'] = $request->input('access', 'playable,preview');
        
        // Add debug parameter to bypass smart filtering if needed
        $params['_debug'] = $request->boolean('debug', false);

        \Log::info('📋 Final search parameters prepared', [
            'total_params' => count($params),
            'params' => $params
        ]);

        // Execute search through service
        $results = $this->soundCloudService->searchTracks($params);

        if ($results === null) {
            \Log::error('❌ SoundCloud search failed - service returned null');
            return response()->json(['error' => 'Failed to search SoundCloud'], 503);
        }

        \Log::info('✅ SoundCloud search completed successfully', [
            'result_count' => count($results->collection ?? []),
            'has_next_page' => isset($results->next_href),
            'response_size_bytes' => strlen(json_encode($results))
        ]);

        return response()->json($results);
    }

    /**
     * Generate SoundCloud embed URL for track player
     * 
     * Handles HTTP POST requests to generate SoundCloud HTML5 embed URLs
     * for track players with customizable player options and styling.
     * 
     * Request Parameters:
     * - track_id: Required SoundCloud track ID
     * - auto_play: Boolean to enable auto-play (default: false)
     * - hide_related: Boolean to hide related tracks (default: true)
     * - show_comments: Boolean to show comments (default: false)
     * - show_user: Boolean to show user info (default: true)
     * - show_reposts: Boolean to show reposts (default: false)
     * - visual: Boolean for visual player mode (default: true)
     * - color: Hex color code for player theme (default: ff5500)
     * 
     * @param Request $request HTTP request with embed parameters
     * @return JsonResponse JSON response with embed URL or error
     */
    public function generateEmbedUrl(Request $request): JsonResponse
    {
        \Log::info('📥 SoundCloud embed URL request received', [
            'method' => 'POST',
            'endpoint' => '/api/soundcloud/embed',
            'parameters' => $request->all(),
            'timestamp' => now()->toISOString()
        ]);

        $trackId = $request->input('track_id');
        
        if (!$trackId) {
            \Log::warning('❌ Embed URL request rejected - missing track_id parameter');
            return response()->json(['error' => 'track_id parameter is required'], 400);
        }

        \Log::info('🎵 Processing embed URL generation', [
            'track_id' => $trackId,
            'track_id_type' => gettype($trackId)
        ]);

        $options = [
            'auto_play' => $request->boolean('auto_play', false),
            'hide_related' => $request->boolean('hide_related', true),
            'show_comments' => $request->boolean('show_comments', false),
            'show_user' => $request->boolean('show_user', true),
            'show_reposts' => $request->boolean('show_reposts', false),
            'visual' => $request->boolean('visual', true),
            'color' => $request->input('color', 'ff5500'),
        ];

        \Log::info('⚙️ Embed player options configured', [
            'options' => $options
        ]);

        try {
            $embedUrl = $this->soundCloudService->generateEmbedUrl($trackId, $options);

            \Log::info('✅ SoundCloud embed URL generated successfully', [
                'track_id' => $trackId,
                'embed_url_length' => strlen($embedUrl),
                'embed_url_preview' => substr($embedUrl, 0, 100) . '...'
            ]);

            return response()->json(['embed_url' => $embedUrl]);
        } catch (\Exception $e) {
            \Log::error('❌ SoundCloud embed URL generation failed', [
                'track_id' => $trackId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to generate embed URL'], 500);
        }
    }

    /**
     * Get SoundCloud user details including real follower count
     * 
     * @param Request $request HTTP request with user_id parameter
     * @return JsonResponse JSON response with user details or error
     */
    public function getUserDetails(Request $request): JsonResponse
    {
        \Log::info('📥 SoundCloud user details request received', [
            'method' => 'GET',
            'endpoint' => '/api/soundcloud/user',
            'parameters' => $request->all(),
            'timestamp' => now()->toISOString()
        ]);

        $userId = $request->input('user_id');
        
        if (!$userId) {
            \Log::warning('❌ User details request rejected - missing user_id parameter');
            return response()->json(['error' => 'user_id parameter is required'], 400);
        }

        if (!SoundCloudService::enabled()) {
            \Log::warning('❌ SoundCloud user details rejected - service not enabled');
            return response()->json(['error' => 'SoundCloud integration is not enabled'], 501);
        }

        try {
            $userDetails = $this->soundCloudService->getUserDetails($userId);

            if ($userDetails === null) {
                \Log::error('❌ SoundCloud user details failed - service returned null');
                return response()->json(['error' => 'Failed to fetch user details'], 503);
            }

            \Log::info('✅ SoundCloud user details completed successfully', [
                'user_id' => $userId,
                'username' => $userDetails->username ?? 'Unknown',
                'followers_count' => $userDetails->followers_count ?? 0
            ]);

            return response()->json($userDetails);
        } catch (\Exception $e) {
            \Log::error('❌ SoundCloud user details failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch user details'], 500);
        }
    }
}