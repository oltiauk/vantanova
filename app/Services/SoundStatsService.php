<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoundStatsService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.soundstats.api_key');
        // Updated to use the working URL from your Python test
        $this->baseUrl = config('services.soundstats.base_url', 'https://soundstat.info');
    }

    public function getMixedRecommendations(array $seedTracks, array $targetFeatures = [], int $limit = 20): array
    {
        try {
            // Use the exact same approach that worked in Python
            $payload = [
                'seed_tracks' => $seedTracks,
                'limit' => $limit,
            ];

            if (!empty($targetFeatures)) {
                $payload['target_features'] = $this->formatTargetFeatures($targetFeatures);
            }

            Log::info('Making request to SoundStats API', [
                'url' => $this->baseUrl . '/api/v1/recommendations/mixed',
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/api/v1/recommendations/mixed', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('SoundStats API success!', [
                    'status' => $response->status(),
                    'track_count' => count($data['track_ids'] ?? [])
                ]);
                return $data ?: ['track_ids' => []];
            }

            Log::error('SoundStats API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            throw new \Exception("SoundStats API error: HTTP {$response->status()} - {$response->body()}");

        } catch (\Exception $e) {
            Log::error('SoundStats API exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new \Exception('SoundStats API connection failed: ' . $e->getMessage());
        }
    }

    private function formatTargetFeatures(array $features): array
    {
        $formatted = [];

        // Handle BPM/tempo features - API expects min_tempo and max_tempo as separate fields
        if (isset($features['bpm_min'])) {
            $formatted['min_tempo'] = (float) $features['bpm_min'];
        }
        
        if (isset($features['bpm_max'])) {
            $formatted['max_tempo'] = (float) $features['bpm_max'];
        }

        // Handle popularity as min/max ranges
        if (isset($features['popularity'])) {
            $popularity = (float) $features['popularity'];
            // Convert single popularity value to a range
            $formatted['min_popularity'] = max(0, $popularity - 10);
            $formatted['max_popularity'] = min(100, $popularity + 10);
        }

        // Handle other audio features directly (like your Python example)
        $directFeatures = ['danceability', 'energy', 'valence', 'acousticness', 'instrumentalness', 'liveness', 'speechiness'];
        
        foreach ($directFeatures as $feature) {
            if (isset($features[$feature])) {
                $formatted[$feature] = (float) $features[$feature];
            }
        }

        // Handle key and mode
        if (isset($features['key'])) {
            $formatted['key'] = (int) $features['key'];
        }
        
        if (isset($features['mode'])) {
            $formatted['mode'] = (int) $features['mode'];
        }

        // Handle key compatibility - convert to key/mode if needed
        if (isset($features['key_compatibility']) && $features['key_compatibility'] && !isset($formatted['key'])) {
            // You might want to derive key/mode from the seed track or use default values
            $formatted['key'] = 1;
            $formatted['mode'] = 1;
        }

        return $formatted;
    }

    public function getTrackAnalysis(string $trackId): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(15)
            ->get($this->baseUrl . '/api/v1/tracks/' . $trackId . '/analysis');

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Track analysis failed: HTTP {$response->status()}");

        } catch (\Exception $e) {
            Log::error('Track analysis failed', ['error' => $e->getMessage(), 'track_id' => $trackId]);
            throw new \Exception('Unable to get track analysis from SoundStats API: ' . $e->getMessage());
        }
    }

    // Test method to verify connection
    public function testConnection(): array
    {
        try {
            $testPayload = [
                'seed_tracks' => ['3n3Ppam7vgaVa1iaRUc9Lp', '7ouMYWpwJ422jRcDASZB7P'],
                'target_features' => [
                    'danceability' => 0.8,
                    'energy' => 0.7
                ],
                'limit' => 10
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/api/v1/recommendations/mixed', $testPayload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? null : $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => null,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}