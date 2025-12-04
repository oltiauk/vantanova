<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\LabelSearchRequest;
use App\Services\SpotifyService;
use App\Services\RapidApiSpotifyService;
use App\Models\SavedTrack;
use App\Models\BlacklistedTrack;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class LabelSearchController extends Controller
{
    public function __construct(
        private readonly SpotifyService $spotifyService,
        private ?RapidApiSpotifyService $rapidApiSpotifyService = null
    )
    {
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
            // Try exact label search first
            $query = 'label:"' . $label . '"';

            if ($includeNew) {
                $query .= ' tag:new';
            }

            if ($includeHipster) {
                $query .= ' tag:hipster';
            }

            if ($releaseYear) {
                $query .= " year:$releaseYear";
            }

            // Search for albums with increased limit
            $searchResults = $this->searchAlbumsWithFallback($query, $limit);

            // If no results with exact label, try broader search
            if (empty($searchResults['albums']['items'])) {
                $broadQuery = '"' . $label . '"'; // Search album/artist names for label

                if ($includeNew) {
                    $broadQuery .= ' tag:new';
                }

                if ($includeHipster) {
                    $broadQuery .= ' tag:hipster';
                }

                if ($releaseYear) {
                    $broadQuery .= " year:$releaseYear";
                }

                Log::info('Trying broader search', ['broad_query' => $broadQuery]);
                $searchResults = $this->searchAlbumsWithFallback($broadQuery, $limit);
            }

            // Debug logging - clean up available_markets to reduce log size
            $cleanAlbums = array_slice($searchResults['albums']['items'] ?? [], 0, 3);
            foreach ($cleanAlbums as &$album) {
                unset($album['available_markets']); // Remove to reduce log verbosity
            }

            Log::info('Spotify search results', [
                'query' => $query,
                'total_results' => $searchResults['albums']['total'] ?? 0,
                'returned_items' => count($searchResults['albums']['items'] ?? []),
                'first_few_albums' => $cleanAlbums
            ]);

            if (empty($searchResults['albums']['items'])) {
                return response()->json(['tracks' => []]);
            }

            // Sort albums by release date (most recent first) and take up to $limit
            $albums = $searchResults['albums']['items'];
            usort($albums, function($a, $b) {
                return strcmp($b['release_date'] ?? '', $a['release_date'] ?? '');
            });
            $recentAlbums = array_slice($albums, 0, $limit);

            // Extract album IDs from the most recent (up to limit)
            $albumIds = array_map(fn($album) => $album['id'], $recentAlbums);

            // Batch get album details with tracks
            $albumsWithTracks = $this->spotifyService->batchGetAlbumsWithTracks($albumIds);

            Log::info('Albums with tracks fetched', [
                'album_count' => count($albumsWithTracks),
                'album_ids' => $albumIds,
                'first_album_track_count' => isset($albumsWithTracks[0]['tracks']['items']) ? count($albumsWithTracks[0]['tracks']['items']) : 0
            ]);

            // Extract most popular track from each album
            $tracks = $this->extractMostPopularTracks($albumsWithTracks, $label, $limit);

            Log::info('Tracks extracted', [
                'track_count' => count($tracks),
                'first_few_tracks' => array_slice($tracks, 0, 2)
            ]);

            // Check saved/banned status for current user
            Log::info('Before addUserPreferenceStatus', ['track_count' => count($tracks)]);

            $tracks = $this->addUserPreferenceStatus($tracks);

            Log::info('After addUserPreferenceStatus', ['track_count' => count($tracks)]);

            // Add followers data to tracks
            $tracks = $this->addFollowersToTracks($tracks);

            Log::info('Final response being sent', [
                'track_count' => count($tracks),
                'response_structure' => ['tracks' => 'array of ' . count($tracks) . ' items']
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

    private function extractMostPopularTracks(array $albums, string $searchLabel, int $limit): array
    {
        // Collect all track IDs first for batch processing
        $allTrackIds = [];
        $albumTrackMap = [];

        foreach ($albums as $album) {
            if (!isset($album['tracks']['items']) || empty($album['tracks']['items'])) {
                continue;
            }

            $albumTrackMap[$album['id']] = [
                'album' => $album,
                'tracks' => $album['tracks']['items']
            ];

            foreach ($album['tracks']['items'] as $track) {
                $allTrackIds[] = $track['id'];
            }
        }

        if (empty($allTrackIds)) {
            return [];
        }

        // Batch get track details for popularity
        $trackDetailsResponse = $this->spotifyService->batchGetTracks($allTrackIds);
        $trackDetails = $trackDetailsResponse['tracks'] ?? [];

        Log::info('Track details response', [
            'track_ids_requested' => count($allTrackIds),
            'track_details_received' => count($trackDetails),
            'first_track_preview_url' => isset($trackDetails[0]['preview_url']) ? $trackDetails[0]['preview_url'] : 'null',
            'preview_url_count' => count(array_filter($trackDetails, fn($track) => $track && !empty($track['preview_url'])))
        ]);

        // Create lookup map for track details
        $trackDetailsMap = [];
        foreach ($trackDetails as $track) {
            if ($track) { // Handle null tracks in response
                $trackDetailsMap[$track['id']] = $track;
            }
        }

        $tracks = [];

        // Now find most popular track per album
        foreach ($albumTrackMap as $albumId => $albumData) {
            $album = $albumData['album'];
            $albumTracks = $albumData['tracks'];

            $mostPopularTrack = null;
            $highestPopularity = -1;

            foreach ($albumTracks as $track) {
                $fullTrack = $trackDetailsMap[$track['id']] ?? null;

                if ($fullTrack && ($fullTrack['popularity'] ?? 0) > $highestPopularity) {
                    $highestPopularity = $fullTrack['popularity'] ?? 0;
                    $mostPopularTrack = $fullTrack;
                }
            }

            // Fallback to first track if no popularity data available
            if (!$mostPopularTrack && !empty($albumTracks)) {
                $firstTrack = $albumTracks[0];
                $mostPopularTrack = $trackDetailsMap[$firstTrack['id']] ?? $firstTrack;
            }

            if ($mostPopularTrack) {
                $trackCount = count($albumTracks);
                $isSingleTrack = $trackCount === 1;
                $actualLabel = $album['label'] ?? $searchLabel;

                $track = [
                    'spotify_id' => $mostPopularTrack['id'],
                    'album_id' => $album['id'],
                    'isrc' => $mostPopularTrack['external_ids']['isrc'] ?? null,
                    'track_name' => $mostPopularTrack['name'],
                    'release_name' => $album['name'],
                    'artist_name' => $mostPopularTrack['artists'][0]['name'] ?? 'Unknown Artist',
                    'artist_id' => $mostPopularTrack['artists'][0]['id'] ?? null,
                    'album_name' => $album['name'],
                    'album_cover' => $album['images'][0]['url'] ?? null,
                    'label' => $actualLabel,
                    'popularity' => $mostPopularTrack['popularity'] ?? 0,
                    'release_date' => $album['release_date'] ?? null,
                    'preview_url' => $mostPopularTrack['preview_url'] ?? null,
                    'track_count' => $trackCount,
                    'is_single_track' => $isSingleTrack,
                    'spotify_release_url' => $isSingleTrack
                        ? ($mostPopularTrack['external_urls']['spotify'] ?? null)
                        : ($album['external_urls']['spotify'] ?? null),
                    'spotify_track_url' => $mostPopularTrack['external_urls']['spotify'] ?? null,
                    'spotify_album_url' => $album['external_urls']['spotify'] ?? null,
                    'spotify_artist_url' => $mostPopularTrack['artists'][0]['external_urls']['spotify'] ?? null,
                ];

                Log::info('Track added to results', [
                    'track_name' => $track['track_name'],
                    'actual_label' => $actualLabel,
                    'search_label' => $searchLabel,
                    'preview_url' => $track['preview_url'] ? 'available' : 'null'
                ]);

                $tracks[] = $track;
            }
        }

        $tracks = $this->filterByLabelMatch($tracks, $searchLabel);

        usort($tracks, fn($a, $b) => $b['popularity'] <=> $a['popularity']);

        return array_slice($tracks, 0, $limit);
    }

    private function filterByLabelMatch(array $tracks, string $searchLabel): array
    {
        return array_filter($tracks, function($track) use ($searchLabel) {
            $actualLabel = $track['label'] ?? '';

            if (empty($actualLabel)) {
                return false;
            }

            // Normalize both strings: lowercase and remove special characters
            $searchNormalized = preg_replace('/[^a-z0-9\s]/i', ' ', strtolower($searchLabel));
            $actualNormalized = preg_replace('/[^a-z0-9\s]/i', ' ', strtolower($actualLabel));
            
            // Split into words and filter empty strings
            $searchWords = array_filter(preg_split('/\s+/', trim($searchNormalized)));
            $actualWords = array_filter(preg_split('/\s+/', trim($actualNormalized)));

            // Check if all search words exist as exact words in the actual label
            $allWordsMatch = true;
            foreach ($searchWords as $searchWord) {
                if (!in_array($searchWord, $actualWords, true)) {
                    $allWordsMatch = false;
                    break;
                }
            }

            // Log::info('Label match check', [
            //     'search_label' => $searchLabel,
            //     'actual_label' => $actualLabel,
            //     'search_words' => $searchWords,
            //     'actual_words' => $actualWords,
            //     'is_match' => $allWordsMatch
            // ]);

            return $allWordsMatch;
        });
    }

    private function addUserPreferenceStatus(array $tracks): array
    {
        $userId = auth()->id();

        if (!$userId) {
            return array_map(fn($track) => array_merge($track, [
                'is_saved' => false,
                'is_banned' => false
            ]), $tracks);
        }

        $savedIsrcs = SavedTrack::getSavedIsrcs($userId);
        $bannedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);

        return array_map(function ($track) use ($savedIsrcs, $bannedIsrcs) {
            $isrc = $track['isrc'];

            return array_merge($track, [
                'is_saved' => $isrc && in_array($isrc, $savedIsrcs),
                'is_banned' => $isrc && in_array($isrc, $bannedIsrcs)
            ]);
        }, $tracks);
    }

    /**
     * Search albums using RapidAPI (with 3-tier dispatcher) first, then fallback to SpotifyService
     */
    private function searchAlbumsWithFallback(string $query, int $limit = 50): array
    {
        // If more than 50 requested, fetch multiple pages directly via Spotify (RapidAPI caps at 50)
        if ($limit > 50) {
            Log::info('🔍 [LABEL SEARCH] Performing multi-page Spotify album search', [
                'query' => $query,
                'limit' => $limit
            ]);

            $items = [];
            $remaining = $limit;
            $offset = 0;

            while ($remaining > 0) {
                $chunk = min(50, $remaining);
                $result = $this->spotifyService->searchAlbums($query, $chunk, $offset);
                $chunkItems = $result['albums']['items'] ?? [];

                if (empty($chunkItems)) {
                    break;
                }

                $items = array_merge($items, $chunkItems);
                $count = count($chunkItems);
                $remaining -= $count;
                $offset += $count;

                // Stop if fewer than requested returned (no more pages)
                if ($count < $chunk) {
                    break;
                }
            }

            return [
                'albums' => [
                    'items' => $items,
                    'total' => count($items),
                ],
            ];
        }

        if (RapidApiSpotifyService::enabled() && $this->rapidApiSpotifyService) {
            Log::info('🔍 [LABEL SEARCH] Attempting RapidAPI album search', [
                'query' => $query,
                'limit' => $limit
            ]);

            try {
                $rapidResult = $this->rapidApiSpotifyService->searchAlbums($query, $limit);
                $items = $rapidResult['albums']['items'] ?? [];

                if (!empty($items)) {
                    Log::info('🔍 [LABEL SEARCH] RapidAPI album search successful', [
                        'query' => $query,
                        'found' => count($items)
                    ]);
                    return $rapidResult;
                }
            } catch (\Exception $e) {
                Log::warning('🔍 [LABEL SEARCH] RapidAPI album search failed, falling back to SpotifyService', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('🔍 [LABEL SEARCH] Falling back to SpotifyService album search', [
            'query' => $query,
            'limit' => $limit
        ]);

        return $this->spotifyService->searchAlbums($query, $limit);
    }

    /**
     * Add followers count to tracks by batch querying artist IDs
     */
    private function addFollowersToTracks(array $tracks): array
    {
        // Extract unique artist IDs from tracks
        $artistIds = [];
        foreach ($tracks as $track) {
            if (!empty($track['artist_id'])) {
                $artistIds[] = $track['artist_id'];
            }
        }

        // Remove duplicates
        $artistIds = array_unique($artistIds);

        // Fetch followers data in batch if RapidAPI Spotify is enabled
        $followersData = [];
        if (!empty($artistIds) && $this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
            try {
                $followersData = $this->rapidApiSpotifyService->getBatchArtistFollowers($artistIds);
                Log::info('📊 [LABEL SEARCH] Fetched followers data', [
                    'artist_count' => count($followersData),
                    'unique_artist_ids' => count($artistIds),
                    'timestamp' => now()->toISOString()
                ]);
            } catch (\Exception $e) {
                Log::warning('📊 [LABEL SEARCH] Failed to fetch followers data', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Add followers to each track
        return array_map(function ($track) use ($followersData) {
            $artistId = $track['artist_id'] ?? null;
            $followers = 0;

            if (!empty($artistId) && isset($followersData[$artistId])) {
                $followers = $followersData[$artistId]['followers'] ?? 0;
            }

            return array_merge($track, [
                'followers' => $followers
            ]);
        }, $tracks);
    }

}
