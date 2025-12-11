<?php

namespace App\Services;

use App\Models\BlacklistedTrack;
use App\Models\SavedTrack;
use Illuminate\Support\Facades\Log;

class LabelSearchService
{
    public function __construct(
        private readonly SpotifyService $spotifyService,
        private ?RapidApiSpotifyService $rapidApiSpotifyService = null
    ) {
    }

    /**
     * Run a label search and return normalized tracks.
     *
     * @param string $label
     * @param array $options ['new' => bool, 'hipster' => bool, 'release_year' => ?string, 'limit' => int]
     * @param int|null $userId
     * @return array
     */
    public function search(string $label, array $options = [], ?int $userId = null): array
    {
        $includeNew = (bool) ($options['new'] ?? false);
        $includeHipster = (bool) ($options['hipster'] ?? false);
        $releaseYear = $options['release_year'] ?? null;
        $limit = max(1, min(100, (int) ($options['limit'] ?? 50)));

        // Build query
        $query = 'label:"' . $label . '"';

        if ($includeNew) {
            $query .= ' tag:new';
        }

        if ($includeHipster) {
            $query .= ' tag:hipster';
        }

        if (!empty($releaseYear)) {
            $query .= " year:{$releaseYear}";
        }

        $searchResults = $this->searchAlbumsWithFallback($query, $limit);

        // If no results with exact label, try broader search
        if (empty($searchResults['albums']['items'])) {
            $broadQuery = '"' . $label . '"';

            if ($includeNew) {
                $broadQuery .= ' tag:new';
            }

            if ($includeHipster) {
                $broadQuery .= ' tag:hipster';
            }

            if (!empty($releaseYear)) {
                $broadQuery .= " year:{$releaseYear}";
            }

            Log::info('Trying broader label search', ['query' => $broadQuery]);
            $searchResults = $this->searchAlbumsWithFallback($broadQuery, $limit);
        }

        if (empty($searchResults['albums']['items'])) {
            return [];
        }

        $albums = $searchResults['albums']['items'];
        usort($albums, fn ($a, $b) => strcmp($b['release_date'] ?? '', $a['release_date'] ?? ''));
        $recentAlbums = array_slice($albums, 0, $limit);

        $albumIds = array_map(fn ($album) => $album['id'], $recentAlbums);
        $albumsWithTracks = $this->spotifyService->batchGetAlbumsWithTracks($albumIds);

        $tracks = $this->extractMostPopularTracks($albumsWithTracks, $label, $limit);
        $tracks = $this->addUserPreferenceStatus($tracks, $userId);
        $tracks = $this->addFollowersToTracks($tracks);

        return $tracks;
    }

    private function extractMostPopularTracks(array $albums, string $searchLabel, int $limit): array
    {
        $allTrackIds = [];
        $albumTrackMap = [];

        foreach ($albums as $album) {
            if (!isset($album['tracks']['items']) || empty($album['tracks']['items'])) {
                continue;
            }

            $albumTrackMap[$album['id']] = [
                'album' => $album,
                'tracks' => $album['tracks']['items'],
            ];

            foreach ($album['tracks']['items'] as $track) {
                $allTrackIds[] = $track['id'];
            }
        }

        if (empty($allTrackIds)) {
            return [];
        }

        $trackDetailsResponse = $this->spotifyService->batchGetTracks($allTrackIds);
        $trackDetails = $trackDetailsResponse['tracks'] ?? [];

        $trackDetailsMap = [];
        foreach ($trackDetails as $track) {
            if ($track) {
                $trackDetailsMap[$track['id']] = $track;
            }
        }

        $tracks = [];

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
                    'album_id' => $albumId,
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

                $tracks[] = $track;
            }
        }

        $tracks = $this->filterByLabelMatch($tracks, $searchLabel);
        usort($tracks, fn ($a, $b) => $b['popularity'] <=> $a['popularity']);

        return array_slice($tracks, 0, $limit);
    }

    private function filterByLabelMatch(array $tracks, string $searchLabel): array
    {
        return array_filter($tracks, function ($track) use ($searchLabel) {
            $actualLabel = $track['label'] ?? '';

            if (empty($actualLabel)) {
                return false;
            }

            $searchNormalized = preg_replace('/[^a-z0-9\s]/i', ' ', strtolower($searchLabel));
            $actualNormalized = preg_replace('/[^a-z0-9\s]/i', ' ', strtolower($actualLabel));

            $searchWords = array_filter(preg_split('/\s+/', trim($searchNormalized)));
            $actualWords = array_filter(preg_split('/\s+/', trim($actualNormalized)));

            foreach ($searchWords as $searchWord) {
                if (!in_array($searchWord, $actualWords, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function addUserPreferenceStatus(array $tracks, ?int $userId): array
    {
        if (!$userId) {
            return array_map(fn ($track) => array_merge($track, [
                'is_saved' => false,
                'is_banned' => false,
            ]), $tracks);
        }

        $savedIsrcs = SavedTrack::getSavedIsrcs($userId);
        $bannedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);

        return array_map(function ($track) use ($savedIsrcs, $bannedIsrcs) {
            $isrc = $track['isrc'] ?? null;

            return array_merge($track, [
                'is_saved' => $isrc && in_array($isrc, $savedIsrcs, true),
                'is_banned' => $isrc && in_array($isrc, $bannedIsrcs, true),
            ]);
        }, $tracks);
    }

    private function searchAlbumsWithFallback(string $query, int $limit = 50): array
    {
        if ($limit > 50) {
            Log::info('🔍 [LABEL SEARCH] Performing multi-page Spotify album search', [
                'query' => $query,
                'limit' => $limit,
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
                'limit' => $limit,
            ]);

            try {
                $rapidResult = $this->rapidApiSpotifyService->searchAlbums($query, $limit);
                $items = $rapidResult['albums']['items'] ?? [];

                if (!empty($items)) {
                    Log::info('🔍 [LABEL SEARCH] RapidAPI album search successful', [
                        'query' => $query,
                        'found' => count($items),
                    ]);
                    return $rapidResult;
                }
            } catch (\Exception $e) {
                Log::warning('🔍 [LABEL SEARCH] RapidAPI album search failed, falling back to SpotifyService', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('🔍 [LABEL SEARCH] Falling back to SpotifyService album search', [
            'query' => $query,
            'limit' => $limit,
        ]);

        return $this->spotifyService->searchAlbums($query, $limit);
    }

    private function addFollowersToTracks(array $tracks): array
    {
        $artistIds = [];
        foreach ($tracks as $track) {
            if (!empty($track['artist_id'])) {
                $artistIds[] = $track['artist_id'];
            }
        }

        $artistIds = array_unique($artistIds);

        $followersData = [];
        if (!empty($artistIds) && $this->rapidApiSpotifyService && RapidApiSpotifyService::enabled()) {
            try {
                $followersData = $this->rapidApiSpotifyService->getBatchArtistFollowers($artistIds);
                Log::info('📊 [LABEL SEARCH] Fetched followers data', [
                    'artist_count' => count($followersData),
                    'unique_artist_ids' => count($artistIds),
                ]);
            } catch (\Exception $e) {
                Log::warning('📊 [LABEL SEARCH] Failed to fetch followers data', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($followersData) && SpotifyService::enabled()) {
            foreach ($artistIds as $artistId) {
                $artist = $this->spotifyService->getArtist($artistId);
                if ($artist && isset($artist['followers']['total'])) {
                    $followersData[$artistId] = [
                        'followers' => (int) $artist['followers']['total'],
                    ];
                }
            }

            Log::info('📊 [LABEL SEARCH] Fallback Spotify followers fetch', [
                'fetched' => count($followersData),
                'requested' => count($artistIds),
            ]);
        }

        return array_map(function ($track) use ($followersData) {
            $artistId = $track['artist_id'] ?? null;
            $followers = 0;

            if (!empty($artistId) && isset($followersData[$artistId])) {
                $followers = $followersData[$artistId]['followers'] ?? 0;
            }

            return array_merge($track, [
                'followers' => $followers,
            ]);
        }, $tracks);
    }
}
