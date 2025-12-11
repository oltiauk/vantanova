<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlacklistedTrack;
use App\Models\LabelWatchlist;
use App\Models\LabelWatchlistSearch;
use App\Models\ListenedTrack;
use App\Models\SavedTrack;
use App\Services\LabelSearchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LabelWatchlistController extends Controller
{
    public function __construct(private readonly LabelSearchService $labelSearchService)
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

        $entries = LabelWatchlist::where('user_id', $userId)
            ->orderBy('normalized_label')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entries,
            'limit' => LabelWatchlist::MAX_FOLLOWED_LABELS,
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
            'label' => ['required', 'string', 'min:2'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentCount = LabelWatchlist::where('user_id', $userId)->count();
        if ($currentCount >= LabelWatchlist::MAX_FOLLOWED_LABELS) {
            return response()->json([
                'success' => false,
                'error' => 'Label watchlist limit reached (30 labels).',
            ], 422);
        }

        $label = trim((string) $request->input('label'));
        $normalized = mb_strtolower($label);

        $entry = LabelWatchlist::updateOrCreate(
            [
                'user_id' => $userId,
                'normalized_label' => $normalized,
            ],
            [
                'label' => $label,
            ]
        );

        // Reset cached search
        LabelWatchlistSearch::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
            'data' => $entry,
        ]);
    }

    public function destroy(string $label): JsonResponse
    {
        if (!$this->tablesExist()) {
            return $this->tablesMissingResponse();
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->unauthorizedResponse();
        }

        $normalized = mb_strtolower($label);

        LabelWatchlist::where('user_id', $userId)
            ->where(function ($query) use ($normalized) {
                $query->where('normalized_label', $normalized)
                    ->orWhere('label', $normalized);
            })
            ->delete();

        LabelWatchlistSearch::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
        ]);
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

        $watchlist = LabelWatchlist::where('user_id', $userId)->get();
        if ($watchlist->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'cached' => false,
                'label_count' => 0,
                'track_count' => 0,
                'cooldown_seconds' => 0,
            ]);
        }

        $cache = LabelWatchlistSearch::firstOrNew(['user_id' => $userId]);
        $forceRefresh = $request->boolean('force_refresh', false);

        if (!$forceRefresh && $cache->last_executed_at && $cache->last_executed_at->gt(now()->subDay())) {
            $status = $this->buildStatusArrays($userId);
            $cachedResults = collect($cache->results ?? [])->map(function ($entry) use ($status) {
                $spotifyTrackId = $entry['spotify_track_id'] ?? null;
                $spotifyAlbumId = $entry['spotify_album_id'] ?? null;
                $isrc = $entry['isrc'] ?? null;

                $identifier = $spotifyTrackId
                    ?: $isrc
                    ?: $spotifyAlbumId
                    ?: ($entry['label_normalized'] ?? 'unknown') . '-' . ($entry['track_title'] ?? '') . '-' . ($entry['release_date'] ?? '');

                $entry['is_banned'] = ($isrc && in_array($isrc, $status['blacklisted_isrcs'], true))
                    || ($spotifyTrackId && in_array($spotifyTrackId, $status['blacklisted_spotify_ids'], true))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $status['blacklisted_spotify_ids'], true));

                $entry['is_saved'] = ($spotifyTrackId && in_array($spotifyTrackId, $status['saved_spotify_ids'], true))
                    || ($isrc && in_array($isrc, $status['saved_isrcs'], true));

                $entry['is_listened'] = in_array($identifier, $status['listened_keys'], true)
                    || ($spotifyTrackId && in_array($spotifyTrackId, $status['listened_keys'], true))
                    || ($isrc && in_array($isrc, $status['listened_keys'], true))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $status['listened_keys'], true));

                return $entry;
            })->toArray();

            $secondsSinceLast = now()->diffInSeconds($cache->last_executed_at);
            $remaining = max(0, 86400 - $secondsSinceLast);

            return response()->json([
                'success' => true,
                'data' => $cachedResults,
                'cached' => true,
                'label_count' => $cache->label_count ?? 0,
                'track_count' => $cache->track_count ?? 0,
                'cooldown_seconds' => $remaining,
                'last_executed_at' => $cache->last_executed_at,
            ]);
        }

        $status = $this->buildStatusArrays($userId);
        $results = [];
        $thirtyDaysAgo = now()->subDays(30);

        foreach ($watchlist as $entry) {
            $tracks = $this->labelSearchService->search($entry->label, [
                'limit' => 100,
            ], $userId);

            foreach ($tracks as $track) {
                $releaseDate = !empty($track['release_date']) ? Carbon::parse($track['release_date']) : null;
                if (!$releaseDate || $releaseDate->lt($thirtyDaysAgo)) {
                    continue;
                }

                $trackCount = $track['track_count'] ?? 1;
                $isSingleTrack = $track['is_single_track'] ?? ($trackCount <= 1);
                $spotifyTrackId = $track['spotify_id'] ?? null;
                $spotifyAlbumId = $track['album_id'] ?? null;
                $isrc = $track['isrc'] ?? null;

                $identifier = $spotifyTrackId
                    ?: $isrc
                    ?: $spotifyAlbumId
                    ?: $entry->normalized_label . '-' . ($track['track_name'] ?? '') . '-' . ($track['release_date'] ?? '');

                $isBanned = ($isrc && in_array($isrc, $status['blacklisted_isrcs'], true))
                    || ($spotifyTrackId && in_array($spotifyTrackId, $status['blacklisted_spotify_ids'], true))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $status['blacklisted_spotify_ids'], true));

                $isSaved = ($spotifyTrackId && in_array($spotifyTrackId, $status['saved_spotify_ids'], true))
                    || ($isrc && in_array($isrc, $status['saved_isrcs'], true));

                $isListened = in_array($identifier, $status['listened_keys'], true)
                    || ($spotifyTrackId && in_array($spotifyTrackId, $status['listened_keys'], true))
                    || ($isrc && in_array($isrc, $status['listened_keys'], true))
                    || ($spotifyAlbumId && in_array($spotifyAlbumId, $status['listened_keys'], true));

                $embedId = $spotifyTrackId ?: $spotifyAlbumId;
                $embedType = $spotifyTrackId ? 'track' : 'album';

                $results[] = [
                    'label' => $entry->label,
                    'label_normalized' => $entry->normalized_label,
                    'artist_name' => $track['artist_name'] ?? null,
                    'artist_id' => $track['artist_id'] ?? null,
                    'followers' => $track['followers'] ?? null,
                    'release_type' => $trackCount > 1 ? 'album' : 'single',
                    'release_title' => $track['release_name'] ?? $track['track_name'] ?? null,
                    'release_name' => $track['release_name'] ?? $track['track_name'] ?? null,
                    'track_title' => $track['track_name'] ?? null,
                    'release_date' => $releaseDate->toDateString(),
                    'share_url' => $track['spotify_release_url'] ?? $track['spotify_track_url'] ?? null,
                    'preview_url' => $track['preview_url'] ?? null,
                    'spotify_track_id' => $spotifyTrackId,
                    'spotify_album_id' => $spotifyAlbumId,
                    'spotify_artist_id' => $track['artist_id'] ?? null,
                    'isrc' => $isrc,
                    'cover_art' => $track['album_cover'] ?? null,
                    'track_count' => $trackCount,
                    'is_single_track' => $isSingleTrack,
                    'embed_id' => $embedId,
                    'embed_type' => $embedType,
                    'is_banned' => $isBanned,
                    'is_saved' => $isSaved,
                    'is_listened' => $isListened,
                ];
            }
        }

        // Deduplicate by identifier
        $deduped = [];
        foreach ($results as $release) {
            $key = $release['spotify_track_id']
                ?? $release['isrc']
                ?? $release['spotify_album_id']
                ?? ($release['label_normalized'] . '-' . ($release['track_title'] ?? '') . '-' . ($release['release_date'] ?? ''));

            if (!isset($deduped[$key])) {
                $deduped[$key] = $release;
            }
        }

        $results = array_values($deduped);

        usort($results, fn ($a, $b) => Carbon::parse($b['release_date'])->timestamp <=> Carbon::parse($a['release_date'])->timestamp);

        $cache->fill([
            'results' => $results,
            'last_executed_at' => now(),
            'expires_at' => now()->addDay(),
            'label_count' => $watchlist->count(),
            'track_count' => count($results),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => $results,
            'cached' => false,
            'label_count' => $watchlist->count(),
            'track_count' => count($results),
            'cooldown_seconds' => 86400,
            'last_executed_at' => $cache->last_executed_at,
        ]);
    }

    private function buildStatusArrays(int $userId): array
    {
        return [
            'blacklisted_isrcs' => BlacklistedTrack::getBlacklistedIsrcs($userId),
            'blacklisted_spotify_ids' => BlacklistedTrack::where('user_id', $userId)
                ->whereNotNull('spotify_id')
                ->pluck('spotify_id')
                ->toArray(),
            'saved_spotify_ids' => SavedTrack::where('user_id', $userId)
                ->whereNotNull('spotify_id')
                ->pluck('spotify_id')
                ->toArray(),
            'saved_isrcs' => SavedTrack::where('user_id', $userId)
                ->whereNotNull('isrc')
                ->pluck('isrc')
                ->toArray(),
            'listened_keys' => ListenedTrack::where('user_id', $userId)
                ->pluck('track_key')
                ->toArray(),
        ];
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('label_watchlists') && Schema::hasTable('label_watchlist_searches');
    }

    private function tablesMissingResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Label watchlist tables are missing. Run the latest migrations.',
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
