<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ArtistWatchlist;
use App\Models\ArtistWatchlistSearch;
use App\Models\BlacklistedArtist;
use App\Models\BlacklistedTrack;
use App\Models\SavedTrack;
use App\Models\ListenedTrack;
use App\Services\RealTimeSpotifyDataService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ArtistWatchlistController extends Controller
{
    public function __construct(private readonly RealTimeSpotifyDataService $spotifyDataService)
    {
    }

    public function index(): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        $entries = ArtistWatchlist::where('user_id', $userId)
            ->orderBy('artist_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
            'limit' => ArtistWatchlist::MAX_FOLLOWED_ARTISTS,
            'count' => $entries->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        $validator = validator($request->all(), [
            'artist_id' => ['required', 'string'],
            'artist_name' => ['required', 'string'],
            'artist_image_url' => ['nullable', 'string'],
            'followers' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentCount = ArtistWatchlist::where('user_id', $userId)->count();
        if ($currentCount >= ArtistWatchlist::MAX_FOLLOWED_ARTISTS) {
            return response()->json([
                'success' => false,
                'error' => 'Artist watchlist limit reached (30 artists).',
            ], 422);
        }

        // Ensure latest data if not provided
        $artistId = $request->string('artist_id')->trim();
        $artistName = $request->string('artist_name')->trim();
        $artistImage = $request->input('artist_image_url');
        $followers = $request->input('followers');

        if (RealTimeSpotifyDataService::enabled()) {
            if (!$artistImage || !$followers) {
                $overview = $this->spotifyDataService->getArtistOverview($artistId);
                if ($overview['success']) {
                    $artistImage = $artistImage ?: Arr::get($overview, 'data.data.artist.visuals.avatarImage.sources.0.url');
                    $followers = $followers ?: Arr::get($overview, 'data.data.artist.stats.followers');
                }
            }
        }

        $watchlist = ArtistWatchlist::updateOrCreate(
            [
                'user_id' => $userId,
                'artist_id' => $artistId,
            ],
            [
                'artist_name' => $artistName,
                'artist_image_url' => $artistImage,
                'followers' => $followers,
            ]
        );

        // Reset cached search
        ArtistWatchlistSearch::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
            'data' => $watchlist,
        ]);
    }

    public function destroy(string $artistId): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        ArtistWatchlist::where('user_id', $userId)
            ->where('artist_id', $artistId)
            ->delete();

        // Reset cache
        ArtistWatchlistSearch::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function searchArtists(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        $validator = validator($request->all(), [
            'query' => ['required', 'string', 'min:2'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!RealTimeSpotifyDataService::enabled()) {
            return response()->json([
                'success' => false,
                'error' => 'Real-time Spotify API is not configured.',
            ], 503);
        }

        $result = $this->spotifyDataService->searchArtists($request->string('query'));

        return response()->json($result);
    }

    public function releases(Request $request): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        $watchlist = ArtistWatchlist::where('user_id', $userId)->get();
        if ($watchlist->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'cached' => false,
                'artist_count' => 0,
                'track_count' => 0,
                'cooldown_seconds' => 0,
            ]);
        }

        $cache = ArtistWatchlistSearch::firstOrNew(['user_id' => $userId]);
        $forceRefresh = $request->boolean('force_refresh', false);

        if (!$forceRefresh && $cache->last_executed_at && $cache->last_executed_at->gt(now()->subDay())) {
            // Refresh ban/saved/listened flags even when using cached releases
            $blacklistedIsrcs = BlacklistedTrack::where('user_id', $userId)
                ->pluck('isrc')
                ->toArray();

            $blacklistedSpotifyIds = BlacklistedTrack::where('user_id', $userId)
                ->pluck('spotify_id')
                ->filter()
                ->values()
                ->toArray();

            $blacklistedArtistIds = BlacklistedArtist::where('user_id', $userId)
                ->pluck('spotify_artist_id')
                ->filter()
                ->values()
                ->toArray();

            $blacklistedArtistNames = BlacklistedArtist::where('user_id', $userId)
                ->pluck('artist_name')
                ->filter()
                ->map(fn ($name) => mb_strtolower($name))
                ->values()
                ->toArray();

            $savedTrackSpotifyIds = SavedTrack::where('user_id', $userId)
                ->pluck('spotify_id')
                ->filter()
                ->values()
                ->toArray();

            $savedTrackIsrcs = SavedTrack::where('user_id', $userId)
                ->pluck('isrc')
                ->filter()
                ->values()
                ->toArray();

            $listenedTrackKeys = ListenedTrack::where('user_id', $userId)
                ->pluck('track_key')
                ->toArray();

            $cachedResults = collect($cache->results ?? [])->map(function ($entry) use (
                $blacklistedIsrcs,
                $blacklistedSpotifyIds,
                $blacklistedArtistIds,
                $blacklistedArtistNames,
                $savedTrackSpotifyIds,
                $savedTrackIsrcs,
                $listenedTrackKeys
            ) {
                $spotifyTrackId = $entry['spotify_track_id'] ?? null;
                $spotifyAlbumId = $entry['spotify_album_id'] ?? null;
                $isrc = $entry['isrc'] ?? null;

                $isBanned = ($isrc && in_array($isrc, $blacklistedIsrcs))
                    || ($spotifyTrackId && in_array($spotifyTrackId, $blacklistedSpotifyIds))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $blacklistedSpotifyIds))
                    || (!empty($entry['spotify_artist_id']) && in_array($entry['spotify_artist_id'], $blacklistedArtistIds))
                    || (isset($entry['artist_name']) && in_array(mb_strtolower($entry['artist_name']), $blacklistedArtistNames));

                $isSaved = ($spotifyTrackId && in_array($spotifyTrackId, $savedTrackSpotifyIds))
                    || ($isrc && in_array($isrc, $savedTrackIsrcs));

                $identifier = $spotifyTrackId
                    ?: $isrc
                    ?: $spotifyAlbumId
                    ?: (($entry['artist_id'] ?? $entry['spotify_artist_id'] ?? 'unknown') . '-' . ($entry['track_title'] ?? $entry['release_title'] ?? '') . '-' . ($entry['release_date'] ?? ''));

                $isListened = in_array($identifier, $listenedTrackKeys)
                    || ($spotifyTrackId && in_array($spotifyTrackId, $listenedTrackKeys))
                    || ($isrc && in_array($isrc, $listenedTrackKeys))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $listenedTrackKeys));

                $entry['is_banned'] = $isBanned;
                $entry['is_saved'] = $isSaved;
                $entry['is_listened'] = $isListened;

                return $entry;
            })->filter(function ($entry) use ($blacklistedIsrcs, $blacklistedSpotifyIds, $blacklistedArtistIds, $blacklistedArtistNames) {
                // Filter out banned tracks
                $isrc = $entry['isrc'] ?? null;
                $spotifyTrackId = $entry['spotify_track_id'] ?? null;
                $spotifyAlbumId = $entry['spotify_album_id'] ?? null;

                // Check if track is banned by ISRC
                if ($isrc && in_array($isrc, $blacklistedIsrcs)) {
                    return false;
                }

                // Check if track is banned by Spotify track ID
                if ($spotifyTrackId && in_array($spotifyTrackId, $blacklistedSpotifyIds)) {
                    return false;
                }

                // Check if album is banned by Spotify album ID
                if ($spotifyAlbumId && in_array($spotifyAlbumId, $blacklistedSpotifyIds)) {
                    return false;
                }

                // Check if artist is banned by Spotify artist ID
                if (!empty($entry['spotify_artist_id']) && in_array($entry['spotify_artist_id'], $blacklistedArtistIds)) {
                    return false;
                }

                // Check if artist is banned by artist name
                if (in_array(mb_strtolower($entry['artist_name'] ?? ''), $blacklistedArtistNames)) {
                    return false;
                }

                return true;
            })->values()->toArray();

            $secondsSinceLast = now()->diffInSeconds($cache->last_executed_at);
            $remaining = max(0, 86400 - $secondsSinceLast);

            return response()->json([
                'success' => true,
                'data' => $cachedResults,
                'cached' => true,
                'artist_count' => $cache->artist_count ?? 0,
                'track_count' => count($cachedResults),
                'cooldown_seconds' => $remaining,
                'last_executed_at' => $cache->last_executed_at,
            ]);
        }

        if (!RealTimeSpotifyDataService::enabled()) {
            return response()->json([
                'success' => false,
                'error' => 'Real-time Spotify API is not configured.',
            ], 503);
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $thirtyDaysAgo = now()->subDays(30);
        $blacklistedIsrcs = BlacklistedTrack::getBlacklistedIsrcs($userId);
        $blacklistedArtistIds = BlacklistedArtist::getBlacklistedArtistIds($userId);
        $blacklistedArtistNames = BlacklistedArtist::where('user_id', $userId)
            ->pluck('artist_name')
            ->map(fn ($name) => mb_strtolower($name))
            ->toArray();

        // Fetch all blacklisted track spotify IDs (in addition to ISRCs)
        $blacklistedSpotifyIds = BlacklistedTrack::where('user_id', $userId)
            ->whereNotNull('spotify_id')
            ->pluck('spotify_id')
            ->toArray();

        // Fetch all saved track spotify IDs and ISRCs for this user
        $savedTrackSpotifyIds = SavedTrack::where('user_id', $userId)
            ->whereNotNull('spotify_id')
            ->pluck('spotify_id')
            ->toArray();
        $savedTrackIsrcs = SavedTrack::where('user_id', $userId)
            ->whereNotNull('isrc')
            ->pluck('isrc')
            ->toArray();

        // Fetch all listened track keys for this user
        $listenedTrackKeys = ListenedTrack::where('user_id', $userId)
            ->pluck('track_key')
            ->toArray();

        Log::debug('🎨 [WATCHLIST] Status arrays loaded', [
            'blacklisted_isrcs_count' => count($blacklistedIsrcs),
            'blacklisted_spotify_ids_count' => count($blacklistedSpotifyIds),
            'saved_spotify_ids_count' => count($savedTrackSpotifyIds),
            'saved_isrcs_count' => count($savedTrackIsrcs),
            'listened_keys_count' => count($listenedTrackKeys),
            'listened_keys_sample' => array_slice($listenedTrackKeys, 0, 5),
        ]);

        foreach ($watchlist as $artist) {
            $overview = $this->spotifyDataService->getArtistOverview($artist->artist_id);
            if (!$overview['success']) {
                $failureCount++;
                Log::warning('🎧 [ARTIST WATCHLIST] Failed to fetch overview', [
                    'artist_id' => $artist->artist_id,
                    'artist_name' => $artist->artist_name,
                    'error' => $overview['error'] ?? 'unknown',
                ]);
                continue;
            }

            $successCount++;
            $artistFollowers = Arr::get($overview, 'data.data.artist.stats.followers', $artist->followers);
            $singles = Arr::get($overview, 'data.data.artist.discography.singles.items', []);
            $albums = Arr::get($overview, 'data.data.artist.discography.albums.items', []);

            $releases = array_merge(
                $this->formatReleases($singles, $artist, $artistFollowers, 'single', $thirtyDaysAgo, $blacklistedIsrcs, $blacklistedSpotifyIds, $savedTrackSpotifyIds, $savedTrackIsrcs, $listenedTrackKeys),
                $this->formatReleases($albums, $artist, $artistFollowers, 'album', $thirtyDaysAgo, $blacklistedIsrcs, $blacklistedSpotifyIds, $savedTrackSpotifyIds, $savedTrackIsrcs, $listenedTrackKeys)
            );

            $results = array_merge($results, $releases);
        }

        // If all API calls failed and we have cached data, return cached data instead
        if ($successCount === 0 && $failureCount > 0 && !empty($cache->results)) {
            Log::warning('🎧 [ARTIST WATCHLIST] All API calls failed, returning cached data', [
                'failed_artists' => $failureCount,
                'cached_results_count' => count($cache->results ?? []),
            ]);

            // Filter out banned tracks from cached results
            $filteredCachedResults = array_values(array_filter($cache->results ?? [], function ($release) use ($blacklistedIsrcs, $blacklistedSpotifyIds, $blacklistedArtistIds, $blacklistedArtistNames) {
                $isrc = $release['isrc'] ?? null;
                $spotifyTrackId = $release['spotify_track_id'] ?? null;
                $spotifyAlbumId = $release['spotify_album_id'] ?? null;

                // Check if track is banned by ISRC
                if ($isrc && in_array($isrc, $blacklistedIsrcs)) {
                    return false;
                }

                // Check if track is banned by Spotify track ID
                if ($spotifyTrackId && in_array($spotifyTrackId, $blacklistedSpotifyIds)) {
                    return false;
                }

                // Check if album is banned by Spotify album ID
                if ($spotifyAlbumId && in_array($spotifyAlbumId, $blacklistedSpotifyIds)) {
                    return false;
                }

                // Check if artist is banned by Spotify artist ID
                if (!empty($release['spotify_artist_id']) && in_array($release['spotify_artist_id'], $blacklistedArtistIds)) {
                    return false;
                }

                // Check if artist is banned by artist name
                if (in_array(mb_strtolower($release['artist_name'] ?? ''), $blacklistedArtistNames)) {
                    return false;
                }

                return true;
            }));

            $secondsSinceLast = $cache->last_executed_at ? now()->diffInSeconds($cache->last_executed_at) : 0;
            $remaining = max(0, 86400 - $secondsSinceLast);

            return response()->json([
                'success' => true,
                'data' => $filteredCachedResults,
                'cached' => true,
                'artist_count' => $cache->artist_count ?? 0,
                'track_count' => count($filteredCachedResults),
                'cooldown_seconds' => $remaining,
                'last_executed_at' => $cache->last_executed_at,
                'api_error' => true,
                'message' => 'Unable to fetch new releases from API. Showing cached results.',
            ]);
        }

        $filtered = array_values(array_filter($results, function ($release) use ($blacklistedIsrcs, $blacklistedSpotifyIds, $blacklistedArtistIds, $blacklistedArtistNames) {
            $isrc = $release['isrc'] ?? null;
            $spotifyTrackId = $release['spotify_track_id'] ?? null;
            $spotifyAlbumId = $release['spotify_album_id'] ?? null;

            // Check if track is banned by ISRC
            if ($isrc && in_array($isrc, $blacklistedIsrcs)) {
                return false;
            }

            // Check if track is banned by Spotify track ID
            if ($spotifyTrackId && in_array($spotifyTrackId, $blacklistedSpotifyIds)) {
                return false;
            }

            // Check if album is banned by Spotify album ID
            if ($spotifyAlbumId && in_array($spotifyAlbumId, $blacklistedSpotifyIds)) {
                return false;
            }

            // Check if artist is banned by Spotify artist ID
            if (!empty($release['spotify_artist_id']) && in_array($release['spotify_artist_id'], $blacklistedArtistIds)) {
                return false;
            }

            // Check if artist is banned by artist name
            if (in_array(mb_strtolower($release['artist_name']), $blacklistedArtistNames)) {
                return false;
            }

            return true;
        }));

        usort($filtered, fn ($a, $b) => Carbon::parse($b['release_date'])->timestamp <=> Carbon::parse($a['release_date'])->timestamp);

        // Only update cache if we got at least some successful results
        if ($successCount > 0) {
            $cache->fill([
                'results' => $filtered,
                'last_executed_at' => now(),
                'expires_at' => now()->addDay(),
                'artist_count' => $watchlist->count(),
                'track_count' => count($filtered),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'data' => $filtered,
            'cached' => false,
            'artist_count' => $watchlist->count(),
            'track_count' => count($filtered),
            'cooldown_seconds' => 86400,
            'last_executed_at' => $cache->last_executed_at,
            'api_partial_failure' => $failureCount > 0 && $successCount > 0,
            'failed_artists' => $failureCount > 0 ? $failureCount : null,
        ]);
    }

    private function formatReleases(
        array $items,
        ArtistWatchlist $artist,
        ?int $followers,
        string $type,
        Carbon $threshold,
        array $blacklistedIsrcs,
        array $blacklistedSpotifyIds,
        array $savedTrackSpotifyIds,
        array $savedTrackIsrcs,
        array $listenedTrackKeys
    ): array {
        $results = [];

        foreach ($items as $item) {
            // Use each release entry once (album/single), not per-track
            $releaseItems = Arr::get($item, 'releases.items');
            if (empty($releaseItems)) {
                $releaseItems = [$item];
            }

            foreach ($releaseItems as $release) {
                $releaseDate = $this->resolveReleaseDate($release);

                if (!$releaseDate) {
                    Log::debug('🎧 [ARTIST WATCHLIST] Skipping release with no date', [
                        'artist_id' => $artist->artist_id,
                        'release_name' => Arr::get($release, 'name'),
                    ]);
                    continue;
                }

                if ($releaseDate->lt($threshold)) {
                    continue;
                }

                $tracks = Arr::get($release, 'tracks.items', []);
                $firstTrack = $tracks[0]['track'] ?? $tracks[0] ?? null;
                $release['tracks']['totalCount'] = Arr::get($release, 'tracks.totalCount') ?: (is_array($tracks) ? count($tracks) : null);

                $results[] = $this->buildReleaseEntry($artist, $release, $firstTrack, $followers, $type, $releaseDate, $blacklistedIsrcs, $blacklistedSpotifyIds, $savedTrackSpotifyIds, $savedTrackIsrcs, $listenedTrackKeys);
            }
        }

        return $results;
    }

    private function buildReleaseEntry(
        ArtistWatchlist $artist,
        array $release,
        ?array $track,
        ?int $followers,
        string $type,
        Carbon $releaseDate,
        array $blacklistedIsrcs,
        array $blacklistedSpotifyIds,
        array $savedTrackSpotifyIds,
        array $savedTrackIsrcs,
        array $listenedTrackKeys
    ): array {
        $shareUrl = Arr::get($release, 'sharingInfo.shareUrl')
            ?: Arr::get($track, 'sharingInfo.shareUrl');
        $spotifyTrackId = $this->extractFirstTrackId($release, $track);
        $spotifyAlbumId = $this->extractId(Arr::get($release, 'uri') ?? Arr::get($release, 'id'));
        $label = Arr::get($track, 'albumOfTrack.label')
            ?: Arr::get($track, 'track.albumOfTrack.label')
            ?: Arr::get($release, 'label');

        $isrc = Arr::get($track, 'track.external_ids.isrc')
            ?: Arr::get($track, 'external_ids.isrc')
            ?: Arr::get($track, 'track.track.external_ids.isrc');

        $trackCount = Arr::get($release, 'tracks.totalCount');
        if (!$trackCount && isset($release['tracks']['items']) && is_array($release['tracks']['items'])) {
            $trackCount = count($release['tracks']['items']);
        }
        $trackCount = $trackCount ?: 1;
        $isAlbum = $spotifyAlbumId !== null && $trackCount > 1;

        // Choose embed: prefer track, otherwise album fallback
        if ($spotifyTrackId) {
            $embedId = $spotifyTrackId;
            $embedType = 'track';
        } elseif ($spotifyAlbumId) {
            $embedId = $spotifyAlbumId;
            $embedType = 'album';
        } else {
            $embedId = null;
            $embedType = 'track';
        }

        // Calculate ban status - check if this track is blacklisted by ISRC or Spotify ID
        $isBanned = ($isrc && in_array($isrc, $blacklistedIsrcs))
            || ($spotifyTrackId && in_array($spotifyTrackId, $blacklistedSpotifyIds))
            || ($spotifyAlbumId && in_array($spotifyAlbumId, $blacklistedSpotifyIds));

        // Calculate saved status - check if this track is saved
        $isSaved = ($spotifyTrackId && in_array($spotifyTrackId, $savedTrackSpotifyIds))
            || ($isrc && in_array($isrc, $savedTrackIsrcs));

        // Calculate listened status - check if this track/album has been listened to
        // Use the same identifier logic as the frontend
        $releaseIdentifier = $spotifyTrackId
            ?: $isrc
            ?: $spotifyAlbumId
            ?: "{$artist->artist_id}-" . Arr::get($track, 'name', Arr::get($release, 'name')) . "-{$releaseDate->toDateString()}";

        // Check both the primary identifier and alternative identifiers
        $isListened = in_array($releaseIdentifier, $listenedTrackKeys)
            || ($spotifyTrackId && in_array($spotifyTrackId, $listenedTrackKeys))
            || ($isrc && in_array($isrc, $listenedTrackKeys))
            || ($spotifyAlbumId && in_array($spotifyAlbumId, $listenedTrackKeys));

        $entry = [
            'artist_id' => $artist->artist_id,
            'artist_name' => $artist->artist_name,
            'artist_image_url' => $artist->artist_image_url,
            'followers' => $followers,
            'label' => $label,
            'release_type' => $type,
            'release_title' => Arr::get($release, 'name'),
            'release_name' => Arr::get($release, 'name'),
            'track_title' => Arr::get($track, 'name') ?? Arr::get($release, 'name'),
            'release_date' => $releaseDate->toDateString(),
            'share_url' => $shareUrl,
            'preview_url' => Arr::get($track, 'preview_url'),
            'spotify_track_id' => $spotifyTrackId,
            'spotify_album_id' => $spotifyAlbumId,
            'isrc' => $isrc,
            'spotify_artist_id' => $artist->artist_id,
            'cover_art' => Arr::get($release, 'coverArt.sources.0.url'),
            'sharing_info' => Arr::get($release, 'sharingInfo') ?: Arr::get($track, 'sharingInfo'),
            'embed_id' => $embedId,
            'embed_type' => $embedType,
            'track_count' => $trackCount,
            'is_single_track' => !$isAlbum,
            'is_banned' => $isBanned,
            'is_saved' => $isSaved,
            'is_listened' => $isListened,
        ];

        // Debug log for first few releases
        static $debugCount = 0;
        if ($debugCount < 3) {
            Log::debug('🎨 [WATCHLIST] Release entry built', [
                'title' => $entry['track_title'],
                'identifier' => $releaseIdentifier,
                'spotify_track_id' => $spotifyTrackId,
                'spotify_album_id' => $spotifyAlbumId,
                'isrc' => $isrc,
                'is_banned' => $isBanned,
                'is_saved' => $isSaved,
                'is_listened' => $isListened,
                'listened_track_keys_sample' => array_slice($listenedTrackKeys, 0, 5),
            ]);
            $debugCount++;
        }

        return $entry;
    }

    private function extractFirstTrackId(array $release, ?array $track): ?string
    {
        $candidates = [
            Arr::get($track, 'uri'),
            Arr::get($track, 'id'),
            Arr::get($release, 'tracks.items.0.track.uri'),
            Arr::get($release, 'tracks.items.0.track.id'),
            Arr::get($release, 'tracks.items.0.uri'),
            Arr::get($release, 'tracks.items.0.id'),
        ];

        foreach ($candidates as $candidate) {
            $id = $this->extractId($candidate);
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    private function resolveReleaseDate(array $release): ?Carbon
    {
        $iso = Arr::get($release, 'date.isoString') ?: Arr::get($release, 'date.iso');
        if ($iso) {
            return Carbon::parse($iso);
        }

        $year = Arr::get($release, 'date.year');
        if (!$year) {
            return null;
        }

        $month = Arr::get($release, 'date.month', 1);
        $day = Arr::get($release, 'date.day', 1);

        return Carbon::createFromDate($year, $month, $day);
    }

    private function extractId(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_contains($value, ':')) {
            $parts = explode(':', $value);
            return end($parts);
        }

        return $value;
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('artist_watchlists') && Schema::hasTable('artist_watchlist_searches');
    }

    private function tablesMissingResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Artist watchlist tables are missing. Run the latest migrations.',
        ], 500);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Authentication required.',
        ], 401);
    }
}
