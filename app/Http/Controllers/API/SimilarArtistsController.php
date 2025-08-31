<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LastfmService;
use App\Services\SpotifyService;
use App\Http\Integrations\Spotify\SpotifyClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimilarArtistsController extends Controller
{
    public function __construct(
        private readonly LastfmService $lastfmService,
        private readonly SpotifyClient $spotifyClient
    ) {
    }

    /**
     * Search for artists by name
     */
    public function searchArtists(Request $request): JsonResponse
    {
        $query = $request->get('query', '');
        
        if (empty(trim($query))) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('DEBUG: Searching artists', [
                'query' => $query,
                'lastfm_enabled' => $this->lastfmService::enabled(),
                'lastfm_used' => $this->lastfmService::used(),
                'lastfm_key_exists' => !empty(config('koel.services.lastfm.key')),
                'lastfm_secret_exists' => !empty(config('koel.services.lastfm.secret')),
            ]);
            
            // First try without Last.fm to test the endpoint
            if (!$this->lastfmService::enabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Last.fm is not enabled. Please check LASTFM_API_KEY and LASTFM_API_SECRET in .env file',
                    'data' => []
                ]);
            }
            
            $artists = $this->lastfmService->searchArtists($query);
            
            Log::info('Search results', ['count' => count($artists)]);
            
            return response()->json([
                'success' => true,
                'data' => $artists
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search artists', [
                'query' => $query,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search artists: ' . $e->getMessage(),
                'data' => [],
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Get similar artists by MBID
     */
    public function getSimilarArtists(Request $request): JsonResponse
    {
        $mbid = $request->get('mbid', '');
        
        if (empty(trim($mbid))) {
            return response()->json([
                'success' => false,
                'message' => 'MBID parameter is required',
                'data' => []
            ]);
        }

        try {
            $similarArtists = $this->lastfmService->getSimilarArtists($mbid);
            
            return response()->json([
                'success' => true,
                'data' => $similarArtists
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get similar artists', [
                'mbid' => $mbid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar artists',
                'data' => []
            ]);
        }
    }

    /**
     * Get artist info with listeners count by MBID
     */
    public function getArtistInfo(Request $request): JsonResponse
    {
        $mbid = $request->get('mbid', '');
        
        if (empty(trim($mbid))) {
            return response()->json([
                'success' => false,
                'message' => 'MBID parameter is required',
                'data' => null
            ]);
        }

        try {
            $artistInfo = $this->lastfmService->getArtistInfoByMbid($mbid);
            
            return response()->json([
                'success' => true,
                'data' => $artistInfo
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get artist info', [
                'mbid' => $mbid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get artist info',
                'data' => null
            ]);
        }
    }

    /**
     * Get listeners count for multiple artists in batch
     */
    public function batchGetArtistListeners(Request $request): JsonResponse
    {
        $mbids = $request->get('mbids', []);
        
        if (!is_array($mbids) || empty($mbids)) {
            return response()->json([
                'success' => false,
                'message' => 'MBIDs array is required',
                'data' => []
            ]);
        }

        try {
            $results = [];
            
            Log::info('Batch processing artist listeners', [
                'mbids_count' => count($mbids),
                'mbids' => $mbids
            ]);
            
            // Batch process with rate limiting
            foreach ($mbids as $mbid) {
                if (!empty(trim($mbid))) {
                    try {
                        $artistInfo = $this->lastfmService->getArtistInfoByMbid($mbid);
                        if ($artistInfo && isset($artistInfo['listeners'])) {
                            $results[$mbid] = [
                                'listeners' => $artistInfo['listeners'],
                                'playcount' => $artistInfo['playcount'] ?? null,
                                'name' => $artistInfo['name'] ?? 'Unknown'
                            ];
                            Log::info('Successfully got listeners and playcount for', [
                                'mbid' => $mbid, 
                                'listeners' => $artistInfo['listeners'],
                                'playcount' => $artistInfo['playcount'] ?? 'N/A'
                            ]);
                        } else {
                            Log::warning('No listeners data for MBID', ['mbid' => $mbid, 'response' => $artistInfo]);
                        }
                    } catch (\Exception $mbidError) {
                        Log::warning('Failed to get artist info for MBID', [
                            'mbid' => $mbid,
                            'error' => $mbidError->getMessage()
                        ]);
                        // Continue with other MBIDs
                        continue;
                    }
                    
                    // Rate limiting: 5 calls per second max
                    usleep(200000); // 0.2 seconds delay
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to batch get artist listeners', [
                'mbids_count' => count($mbids),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get artist listeners',
                'data' => []
            ]);
        }
    }

    /**
     * Get Spotify preview tracks for an artist
     */
    public function getSpotifyPreview(Request $request): JsonResponse
    {
        $artistName = $request->get('artist_name', '');
        
        if (empty(trim($artistName))) {
            return response()->json([
                'success' => false,
                'message' => 'Artist name parameter is required',
                'data' => []
            ]);
        }

        try {
            Log::info('Getting Spotify preview for artist', [
                'artist' => $artistName,
                'spotify_enabled' => SpotifyService::enabled(),
            ]);
            
            if (!SpotifyService::enabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spotify integration is not enabled',
                    'data' => []
                ]);
            }
            
            // Search for artist on Spotify with exact match
            $artistResults = $this->spotifyClient->search($artistName, 'artist', ['limit' => 10]);
            
            if (empty($artistResults['artists']['items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist not found on Spotify',
                    'data' => []
                ]);
            }
            
            // Find exact match or closest match
            $spotifyArtist = null;
            $artistNameLower = strtolower($artistName);
            
            foreach ($artistResults['artists']['items'] as $artist) {
                if (strtolower($artist['name']) === $artistNameLower) {
                    $spotifyArtist = $artist;
                    break;
                }
            }
            
            // If no exact match found, use the first result
            if (!$spotifyArtist) {
                $spotifyArtist = $artistResults['artists']['items'][0];
            }
            
            $artistId = $spotifyArtist['id'];
            
            // Get top tracks for the artist
            $topTracksResult = $this->spotifyClient->getArtistTopTracks($artistId, ['market' => 'US']);
            
            // Get first 3 tracks with preview URLs and oembed data
            $tracks = [];
            foreach ($topTracksResult['tracks'] as $track) {
                if (count($tracks) >= 3) break;
                
                // Get Spotify oembed data for this track
                $oembedData = $this->getSpotifyOEmbedData($track['id']);
                
                $tracks[] = [
                    'id' => $track['id'],
                    'name' => $track['name'],
                    'artists' => array_map(fn($artist) => ['name' => $artist['name']], $track['artists']),
                    'preview_url' => $track['preview_url'] ?? null,
                    'external_url' => $track['external_urls']['spotify'] ?? null,
                    'duration_ms' => $track['duration_ms'] ?? null,
                    'oembed' => $oembedData,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'artist' => [
                        'id' => $spotifyArtist['id'],
                        'name' => $spotifyArtist['name'],
                        'external_url' => $spotifyArtist['external_urls']['spotify'] ?? null,
                    ],
                    'tracks' => $tracks
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get Spotify preview', [
                'artist' => $artistName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Spotify preview: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get Spotify oEmbed data for a track
     */
    private function getSpotifyOEmbedData(string $spotifyTrackId): ?array
    {
        try {
            $spotifyUrl = "https://open.spotify.com/track/$spotifyTrackId";
            
            $response = \Illuminate\Support\Facades\Http::get("https://open.spotify.com/oembed", [
                'url' => $spotifyUrl,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $oEmbedData = $response->json();
                Log::info("🎵 Similar Artists: Got Spotify oEmbed data", [
                    'spotify_track_id' => $spotifyTrackId
                ]);
                return $oEmbedData;
            } else {
                Log::warning("🎵 Similar Artists: Failed to get oEmbed", [
                    'spotify_track_id' => $spotifyTrackId,
                    'status' => $response->status()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Similar Artists: Spotify oEmbed error', [
                'track_id' => $spotifyTrackId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}