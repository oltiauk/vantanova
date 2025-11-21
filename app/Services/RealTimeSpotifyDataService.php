<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RealTimeSpotifyDataService
{
    private string $baseUrl;
    private string $host;
    private string $key;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.realtime_spotify.base_url', ''), '/');
        $this->host = config('services.realtime_spotify.host', 'real-time-spotify-data-scraper.p.rapidapi.com');
        $this->key = (string) config('services.realtime_spotify.key', '');
    }

    public static function enabled(): bool
    {
        return !empty(config('services.realtime_spotify.key'));
    }

    private function makeRequest(string $endpoint, array $params = []): array
    {
        if (!static::enabled()) {
            return ['success' => false, 'error' => 'Real-time Spotify API not configured'];
        }

        $url = "{$this->baseUrl}{$endpoint}";

        try {
            $response = Http::withHeaders([
                'X-RapidAPI-Key' => $this->key,
                'X-RapidAPI-Host' => $this->host,
            ])
                ->timeout(30)
                ->get($url, $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $errorBody = $response->body();
            $errorJson = $response->json();
            
            Log::warning('🎧 [REALTIME SPOTIFY] Request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $errorBody,
                'json' => $errorJson,
            ]);

            $errorMessage = $errorJson['message'] ?? $errorJson['error'] ?? $errorBody ?? 'Unknown error';
            
            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('🎧 [REALTIME SPOTIFY] Request exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function searchArtists(string $query, int $limit = 10): array
    {
        $result = $this->makeRequest('/search/', [
            'q' => $query,
            'type' => 'artists',
            'limit' => $limit,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $items = Arr::get($result, 'data.artists.items', []);
        $artists = [];

        foreach ($items as $item) {
            $data = $item['data'] ?? $item;
            $uri = Arr::get($data, 'uri', Arr::get($data, 'id'));
            $artistId = $this->extractSpotifyId($uri);

            if (!$artistId) {
                continue;
            }

            $artists[] = [
                'id' => $artistId,
                'name' => Arr::get($data, 'profile.name', 'Unknown Artist'),
                'image' => Arr::get($data, 'visuals.avatarImage.sources.0.url'),
                'followers' => Arr::get($data, 'stats.followers'),
                'genres' => Arr::pluck(Arr::get($data, 'genres.items', []), 'name'),
                'share_url' => Arr::get($data, 'sharingInfo.shareUrl'),
            ];
        }

        return ['success' => true, 'data' => $artists];
    }

    public function getArtistOverview(string $artistId): array
    {
        return $this->makeRequest('/artist_overview/', ['id' => $artistId]);
    }

    private function extractSpotifyId(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'spotify:artist:')) {
            return substr($value, strlen('spotify:artist:'));
        }

        return $value;
    }
}

