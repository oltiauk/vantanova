<?php

namespace App\Services;

use App\Http\Integrations\Lastfm\LastfmConnector;
use App\Http\Integrations\Lastfm\Requests\GetAlbumInfoRequest;
use App\Http\Integrations\Lastfm\Requests\GetArtistInfoRequest;
use App\Http\Integrations\Lastfm\Requests\GetSessionKeyRequest;
use App\Http\Integrations\Lastfm\Requests\GetTrackInfoRequest;
use App\Http\Integrations\Lastfm\Requests\ScrobbleRequest;
use App\Http\Integrations\Lastfm\Requests\ToggleLoveTrackRequest;
use App\Http\Integrations\Lastfm\Requests\UpdateNowPlayingRequest;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use App\Services\Contracts\MusicEncyclopedia;
use App\Values\AlbumInformation;
use App\Values\ArtistInformation;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LastfmService implements MusicEncyclopedia
{
    public function __construct(private readonly LastfmConnector $connector)
    {
    }

    /**
     * Determine if our application is using Last.fm.
     */
    public static function used(): bool
    {
        return (bool) config('koel.services.lastfm.key');
    }

    /**
     * Determine if Last.fm integration is enabled.
     */
    public static function enabled(): bool
    {
        return config('koel.services.lastfm.key') && config('koel.services.lastfm.secret');
    }

    public function getArtistInformation(Artist $artist): ?ArtistInformation
    {
        if ($artist->is_unknown || $artist->is_various) {
            return null;
        }

        return rescue_if(static::enabled(), function () use ($artist): ?ArtistInformation {
            return $this->connector->send(new GetArtistInfoRequest($artist))->dto();
        });
    }

    public function getAlbumInformation(Album $album): ?AlbumInformation
    {
        if ($album->is_unknown || $album->artist->is_unknown) {
            return null;
        }

        return rescue_if(static::enabled(), function () use ($album): ?AlbumInformation {
            return $this->connector->send(new GetAlbumInfoRequest($album))->dto();
        });
    }

    public function scrobble(Song $song, User $user, int $timestamp): void
    {
        rescue(fn () => $this->connector->send(new ScrobbleRequest($song, $user, $timestamp)));
    }

    public function toggleLoveTrack(Song $song, User $user, bool $love): void
    {
        rescue(fn () => $this->connector->send(new ToggleLoveTrackRequest($song, $user, $love)));
    }

    /**
     * @param Collection<array-key, Song> $songs
     */
    public function batchToggleLoveTracks(Collection $songs, User $user, bool $love): void
    {
        $generatorCallback = static function () use ($songs, $user, $love): Generator {
            foreach ($songs as $song) {
                yield new ToggleLoveTrackRequest($song, $user, $love);
            }
        };

        $this->connector
            ->pool($generatorCallback)
            ->send()
            ->wait();
    }

    public function updateNowPlaying(Song $song, User $user): void
    {
        rescue(fn () => $this->connector->send(new UpdateNowPlayingRequest($song, $user)));
    }

    public function getSessionKey(string $token): ?string
    {
        return object_get($this->connector->send(new GetSessionKeyRequest($token))->object(), 'session.key');
    }

    public function setUserSessionKey(User $user, ?string $sessionKey): void
    {
        $user->preferences->lastFmSessionKey = $sessionKey;
        $user->save();
    }

    /**
     * Get track information including play count and listeners from Last.fm
     */
    public function getTrackInformation(string $artist, string $track): ?array
    {
        if (!static::enabled()) {
            return null;
        }

        return rescue(function () use ($artist, $track): ?array {
            Log::info('🎵 Single LastFM request', ['artist' => $artist, 'track' => $track]);
            
            $response = $this->connector->send(new GetTrackInfoRequest($artist, $track));
            
            Log::info('🎵 Single LastFM response', [
                'successful' => $response->successful(),
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 300)
            ]);
            
            return $response->dto();
        });
    }

    /**
     * Get track information for multiple tracks in batch
     * 
     * @param array<array{artist: string, track: string}> $tracks
     * @return array<string, array>
     */
    public function batchGetTrackInformation(array $tracks): array
    {
        Log::info('🎵 LastFM batch service starting', [
            'enabled' => static::enabled(),
            'used' => static::used(),
            'tracks_count' => count($tracks),
            'config_key_set' => !empty(config('koel.services.lastfm.key')),
            'config_secret_set' => !empty(config('koel.services.lastfm.secret'))
        ]);

        if (!static::enabled() || empty($tracks)) {
            Log::warning('🎵 LastFM service not enabled or no tracks provided', [
                'enabled' => static::enabled(),
                'tracks_empty' => empty($tracks)
            ]);
            return [];
        }

        $generatorCallback = static function () use ($tracks): Generator {
            foreach ($tracks as $track) {
                Log::info('🎵 Creating LastFM request for', [
                    'artist' => $track['artist'],
                    'track' => $track['track']
                ]);
                yield new GetTrackInfoRequest($track['artist'], $track['track']);
            }
        };

        Log::info('🎵 Using sequential single requests instead of pool for reliability');
        
        // Use sequential single requests instead of pool
        $results = [];
        foreach ($tracks as $track) {
            $trackKey = strtolower($track['artist'] . '|' . $track['track']);
            
            try {
                $singleResult = $this->getTrackInformation($track['artist'], $track['track']);
                if ($singleResult) {
                    $results[$trackKey] = $singleResult;
                }
                
                // Small delay to respect LastFM rate limits (5 calls per second)
                usleep(200000); // 0.2 seconds delay
                
            } catch (\Exception $e) {
                Log::warning('🎵 Failed to get LastFM info for track', [
                    'track' => $track['artist'] . ' - ' . $track['track'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }
}
