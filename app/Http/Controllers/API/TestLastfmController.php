<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LastfmService;
use Illuminate\Http\JsonResponse;

class TestLastfmController extends Controller
{
    public function __construct(private readonly LastfmService $lastfmService)
    {
    }

    public function __invoke(): JsonResponse
    {
        $enabled = LastfmService::enabled();
        $used = LastfmService::used();
        
        $testStats = null;
        if ($enabled) {
            try {
                // Test single track first
                $testStats = $this->lastfmService->getTrackInformation('Imagine Dragons', 'Warriors');
                
                // Test batch call  
                $batchTest = $this->lastfmService->batchGetTrackInformation([
                    ['artist' => 'Imagine Dragons', 'track' => 'Warriors'],
                    ['artist' => 'Sean Paul', 'track' => 'Temperature']
                ]);
                
                $testStats['batch_test'] = $batchTest;
                
            } catch (\Exception $e) {
                $testStats = [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }
        
        return response()->json([
            'lastfm_enabled' => $enabled,
            'lastfm_used' => $used,
            'config_key' => config('koel.services.lastfm.key') ? 'Set' : 'Not set',
            'config_secret' => config('koel.services.lastfm.secret') ? 'Set' : 'Not set',
            'test_stats' => $testStats
        ]);
    }
}