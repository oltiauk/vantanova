<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RapidApiSpotifyService;
use App\Http\Controllers\API\SimilarArtistsController;
use Illuminate\Http\Request;

class TestSimilarArtistsBackups extends Command
{
    protected $signature = 'test:similar-artists-backups 
                            {--test-type=all : Type of test to run (all, search, similar, batch, preview, connectivity)}
                            {--artist-id=4gzpq5DPGxSnKTe4SA8HAU : Artist ID for testing}
                            {--artist-name=Coldplay : Artist name for testing}
                            {--query=The Beatles : Search query for testing}
                            {--limit=3 : Number of results to fetch}
                            {--detailed : Show detailed output}';

    protected $description = 'Test the Similar Artists backup systems and fallback chains';

    public function handle()
    {
        $this->info('🧪 Testing Similar Artists Backup Systems');
        $this->line('=' . str_repeat('=', 50));

        $testType = $this->option('test-type');
        $verbose = $this->option('detailed');

        switch ($testType) {
            case 'connectivity':
                $this->testApiConnectivity($verbose);
                break;
            case 'search':
                $this->testSearchFunctionality($verbose);
                break;
            case 'similar':
                $this->testSimilarArtistsFunctionality($verbose);
                break;
            case 'batch':
                $this->testBatchFollowersFunctionality($verbose);
                break;
            case 'preview':
                $this->testPreviewFunctionality($verbose);
                break;
            case 'all':
            default:
                $this->runAllTests($verbose);
                break;
        }

        $this->line('');
        $this->info('🎯 Testing completed!');
        $this->line('Check logs for detailed fallback chain information:');
        $this->line('tail -f storage/logs/laravel.log | grep "SIMILAR ARTISTS"');
    }

    private function testApiConnectivity($verbose = false)
    {
        $this->info('🔍 Testing API Connectivity...');
        
        $services = [
            'Spotify81' => [
                'url' => 'https://spotify81.p.rapidapi.com/search',
                'host' => 'spotify81.p.rapidapi.com',
                'params' => ['q' => 'test', 'type' => 'artists', 'limit' => 1]
            ],
            'Spotify-web2' => [
                'url' => 'https://spotify-web2.p.rapidapi.com/search',
                'host' => 'spotify-web2.p.rapidapi.com',
                'params' => ['q' => 'test', 'type' => 'artists', 'limit' => 1]
            ],
            'Spotify23' => [
                'url' => 'https://spotify23.p.rapidapi.com/search/',
                'host' => 'spotify23.p.rapidapi.com',
                'params' => ['q' => 'test', 'type' => 'multi', 'limit' => 1]
            ]
        ];

        $results = [];
        foreach ($services as $name => $config) {
            $this->line("  Testing {$name}...");
            
            try {
                $response = Http::withHeaders([
                    'X-RapidAPI-Key' => config('services.rapidapi.key'),
                    'X-RapidAPI-Host' => $config['host']
                ])->timeout(10)->get($config['url'], $config['params']);

                $status = $response->successful() ? '✅' : '❌';
                $this->line("    {$status} {$name}: " . $response->status());
                
                $results[$name] = [
                    'status' => $response->status(),
                    'success' => $response->successful(),
                    'response_time' => $response->transferStats?->getHandlerStat('total_time') ?? 'N/A'
                ];

                if ($verbose && $response->successful()) {
                    $data = $response->json();
                    $this->line("      Response time: " . $results[$name]['response_time'] . "s");
                    if (isset($data['artists']['items'])) {
                        $this->line("      Found " . count($data['artists']['items']) . " artists");
                    }
                }
                
            } catch (\Exception $e) {
                $this->line("    ❌ {$name}: Error - " . $e->getMessage());
                $results[$name] = [
                    'status' => 'error',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->line('');
        $this->displayConnectivityResults($results);
    }

    private function testSearchFunctionality($verbose = false)
    {
        $this->info('🔍 Testing Search Functionality...');
        
        $query = $this->option('query');
        $limit = $this->option('limit');
        
        $this->line("  Testing search for: '{$query}' (limit: {$limit})");
        
        try {
            // Create a mock request
            $request = new Request([
                'query' => $query,
                'limit' => $limit
            ]);
            
            // Create controller instance
            $controller = app(SimilarArtistsController::class);
            
            // Call the search method directly
            $response = $controller->searchArtists($request);
            $data = $response->getData(true);
            
            $status = $data['success'] ? '✅' : '❌';
            $count = count($data['data'] ?? []);
            
            $this->line("    {$status} Found {$count} artists");
            
            if ($data['success'] && !empty($data['data'])) {
                $this->line("    Sample results:");
                foreach (array_slice($data['data'], 0, 2) as $artist) {
                    $this->line("      - {$artist['name']}");
                }
            } else {
                $this->line("    ❌ Error: " . ($data['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->line("    ❌ Exception: " . $e->getMessage());
        }
        
        $this->line('');
    }

    private function testSimilarArtistsFunctionality($verbose = false)
    {
        $this->info('🎵 Testing Similar Artists Functionality...');
        
        $artistId = $this->option('artist-id');
        $artistName = $this->option('artist-name');
        $limit = $this->option('limit');
        
        $this->line("  Testing similar artists for: {$artistName} ({$artistId})");
        
        try {
            // Create a mock request
            $request = new Request([
                'artist_id' => $artistId,
                'limit' => $limit
            ]);
            
            // Create controller instance
            $controller = app(SimilarArtistsController::class);
            
            // Call the similar artists method directly
            $response = $controller->getSimilarArtists($request);
            $data = $response->getData(true);
            
            $status = $data['success'] ? '✅' : '❌';
            $count = count($data['data'] ?? []);
            
            $this->line("    {$status} Found {$count} similar artists");
            
            if ($data['success'] && !empty($data['data'])) {
                $this->line("    Sample similar artists:");
                foreach (array_slice($data['data'], 0, 2) as $artist) {
                    $this->line("      - {$artist['name']}");
                }
            } else {
                $this->line("    ❌ Error: " . ($data['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->line("    ❌ Exception: " . $e->getMessage());
        }
        
        $this->line('');
    }

    private function testBatchFollowersFunctionality($verbose = false)
    {
        $this->info('📊 Testing Batch Followers Functionality...');
        
        $artistId = $this->option('artist-id');
        $this->line("  Testing batch followers for: {$artistId}");
        
        try {
            // Create a mock request
            $request = new Request([
                'artist_ids' => [$artistId]
            ]);
            
            // Create controller instance
            $controller = app(SimilarArtistsController::class);
            
            // Call the batch followers method directly
            $response = $controller->batchGetArtistListeners($request);
            $data = $response->getData(true);
            
            $status = $data['success'] ? '✅' : '❌';
            $count = count($data['data'] ?? []);
            
            $this->line("    {$status} Retrieved followers for {$count} artists");
            
            if ($data['success'] && !empty($data['data'])) {
                foreach ($data['data'] as $id => $followers) {
                    $followerCount = is_array($followers) ? ($followers['total'] ?? $followers['followers'] ?? 0) : $followers;
                    $this->line("      Artist {$id}: " . number_format($followerCount) . " followers");
                }
            } else {
                $this->line("    ❌ Error: " . ($data['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->line("    ❌ Exception: " . $e->getMessage());
        }
        
        $this->line('');
    }

    private function testPreviewFunctionality($verbose = false)
    {
        $this->info('🎵 Testing Preview Functionality...');
        
        $artistId = $this->option('artist-id');
        $artistName = $this->option('artist-name');
        $limit = 1;
        
        $this->line("  Testing preview for: {$artistName} ({$artistId})");
        
        try {
            // Create a mock request
            $request = new Request([
                'artist_id' => $artistId,
                'artist_name' => $artistName,
                'limit' => $limit
            ]);
            
            // Create controller instance
            $controller = app(SimilarArtistsController::class);
            
            // Call the preview method directly
            $response = $controller->getSpotifyPreview($request);
            $data = $response->getData(true);
            
            $status = $data['success'] ? '✅' : '❌';
            $trackCount = count($data['data']['tracks'] ?? []);
            
            $this->line("    {$status} Found {$trackCount} preview tracks");
            
            if ($data['success'] && !empty($data['data']['tracks'])) {
                $track = $data['data']['tracks'][0];
                $this->line("      Sample track: {$track['name']}");
                if (!empty($track['external_url'])) {
                    $this->line("      Spotify URL: {$track['external_url']}");
                }
            } else {
                $this->line("    ❌ Error: " . ($data['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            $this->line("    ❌ Exception: " . $e->getMessage());
        }
        
        $this->line('');
    }

    private function runAllTests($verbose = false)
    {
        $this->testApiConnectivity($verbose);
        $this->testSearchFunctionality($verbose);
        $this->testSimilarArtistsFunctionality($verbose);
        $this->testBatchFollowersFunctionality($verbose);
        $this->testPreviewFunctionality($verbose);
    }

    private function displayConnectivityResults($results)
    {
        $this->info('📊 Connectivity Results:');
        
        $totalServices = count($results);
        $workingServices = count(array_filter($results, fn($r) => $r['success']));
        
        $this->line("  Working services: {$workingServices}/{$totalServices}");
        
        foreach ($results as $service => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $this->line("  {$status} {$service}: " . $result['status']);
            
            if ($result['success'] && isset($result['response_time'])) {
                $this->line("      Response time: " . $result['response_time'] . "s");
            }
        }
        
        $this->line('');
    }
}
