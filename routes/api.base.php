<?php

use App\Facades\YouTube;
use App\Helpers\Uuid;
use App\Http\Controllers\API\ActivateLicenseController;
use App\Http\Controllers\API\AlbumController;
use App\Http\Controllers\API\AlbumSongController;
use App\Http\Controllers\API\ArtistAlbumController;
use App\Http\Controllers\API\ArtistController;
use App\Http\Controllers\API\ArtistSongController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CheckResourcePermissionController;
use App\Http\Controllers\API\DisconnectFromLastfmController;
use App\Http\Controllers\API\ExcerptSearchController;
use App\Http\Controllers\API\FetchAlbumInformationController;
use App\Http\Controllers\API\FetchAlbumThumbnailController;
use App\Http\Controllers\API\FetchArtistInformationController;
use App\Http\Controllers\API\FetchDemoCreditsController;
use App\Http\Controllers\API\FetchFavoriteSongsController;
use App\Http\Controllers\API\FetchInitialDataController;
use App\Http\Controllers\API\FetchOverviewController;
use App\Http\Controllers\API\FetchRandomSongsInGenreController;
use App\Http\Controllers\API\FetchRecentlyPlayedSongController;
use App\Http\Controllers\API\FetchSongsForQueueController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\GenreController;
use App\Http\Controllers\API\GenreSongController;
use App\Http\Controllers\API\GetOneTimeTokenController;
use App\Http\Controllers\API\LambdaSongController as S3SongController;
use App\Http\Controllers\API\LikeMultipleSongsController;
use App\Http\Controllers\API\MediaBrowser\FetchFolderSongsController;
use App\Http\Controllers\API\MediaBrowser\FetchRecursiveFolderSongsController;
use App\Http\Controllers\API\MediaBrowser\FetchSubfoldersController;
use App\Http\Controllers\API\MediaBrowser\PaginateFolderSongsController;
use App\Http\Controllers\API\MovePlaylistSongsController;
use App\Http\Controllers\API\PlaylistCollaboration\AcceptPlaylistCollaborationInviteController;
use App\Http\Controllers\API\PlaylistCollaboration\CreatePlaylistCollaborationTokenController;
use App\Http\Controllers\API\PlaylistCollaboration\PlaylistCollaboratorController;
use App\Http\Controllers\API\PlaylistController;
use App\Http\Controllers\API\PlaylistCoverController;
use App\Http\Controllers\API\PlaylistFolderController;
use App\Http\Controllers\API\PlaylistFolderPlaylistController;
use App\Http\Controllers\API\PlaylistSongController;
use App\Http\Controllers\API\Podcast\FetchEpisodeController;
use App\Http\Controllers\API\Podcast\PodcastController;
use App\Http\Controllers\API\Podcast\PodcastEpisodeController;
use App\Http\Controllers\API\Podcast\UnsubscribeFromPodcastController;
use App\Http\Controllers\API\PrivatizeSongsController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PublicizeSongsController;
use App\Http\Controllers\API\QueueStateController;
use App\Http\Controllers\API\RegisterPlayController;
use App\Http\Controllers\API\ResetPasswordController;
use App\Http\Controllers\API\ScrobbleController;
use App\Http\Controllers\API\SearchSoundCloudController;
use App\Http\Controllers\API\SearchYouTubeController;
use App\Http\Controllers\API\SetLastfmSessionKeyController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\SongController;
use App\Http\Controllers\API\SongSearchController;
use App\Http\Controllers\API\ToggleLikeSongController;
use App\Http\Controllers\API\UnlikeMultipleSongsController;
use App\Http\Controllers\API\UpdatePlaybackStatusController;
use App\Http\Controllers\API\UpdateUserPreferenceController;
use App\Http\Controllers\API\UploadAlbumCoverController;
use App\Http\Controllers\API\UploadArtistImageController;
use App\Http\Controllers\API\UploadController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserInvitationController;
use App\Http\Controllers\API\MusicDiscoveryController;
use App\Http\Controllers\API\MusicPreferencesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Pusher\Pusher;

Route::prefix('api')->middleware('api')->group(static function (): void {
    Route::get('ping', static fn () => null);

    Route::post('me', [AuthController::class, 'login'])->name('auth.login');
    Route::post('me/otp', [AuthController::class, 'loginUsingOneTimeToken']);

    Route::delete('me', [AuthController::class, 'logout']);

    Route::post('forgot-password', ForgotPasswordController::class);
    Route::post('reset-password', ResetPasswordController::class);

    Route::get('invitations', [UserInvitationController::class, 'get']);
    Route::post('invitations/accept', [UserInvitationController::class, 'accept']);

    Route::middleware('auth')->group(static function (): void {
        Route::get('one-time-token', GetOneTimeTokenController::class);
        Route::post('broadcasting/auth', static function (Request $request) {
            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'encrypted' => true,
                ]
            );

            return $pusher->authorizeChannel($request->input('channel_name'), $request->input('socket_id'));
        })->name('broadcasting.auth');

        Route::get('overview', FetchOverviewController::class);
        Route::get('data', FetchInitialDataController::class);

        Route::get('queue/fetch', FetchSongsForQueueController::class);
        Route::put('queue/playback-status', UpdatePlaybackStatusController::class);
        Route::get('queue/state', [QueueStateController::class, 'show']);
        Route::put('queue/state', [QueueStateController::class, 'update']);

        Route::put('settings', [SettingController::class, 'update']);

        Route::apiResource('albums', AlbumController::class);
        Route::apiResource('albums.songs', AlbumSongController::class);

        Route::apiResource('artists', ArtistController::class);
        Route::apiResource('artists.albums', ArtistAlbumController::class);
        Route::apiResource('artists.songs', ArtistSongController::class);

        Route::post('songs/{song}/scrobble', ScrobbleController::class)->where(['song' => Uuid::REGEX]);

        Route::apiResource('songs', SongController::class)
            ->except('update', 'destroy')
            ->where(['song' => Uuid::REGEX]);

        Route::put('songs', [SongController::class, 'update']);
        Route::delete('songs', [SongController::class, 'destroy']);

        // Fetch songs under several folder paths (may include multiple nested levels).
        // This is a POST request because the folder paths may be long.
        Route::post('songs/by-folders', FetchRecursiveFolderSongsController::class);

        // Fetch songs **directly** in a specific folder path (or the media root if no path is specified)
        Route::get('songs/in-folder', FetchFolderSongsController::class);

        Route::post('upload', UploadController::class);

        // Interaction routes
        Route::post('interaction/play', RegisterPlayController::class);
        Route::post('interaction/like', ToggleLikeSongController::class);
        Route::post('interaction/batch/like', LikeMultipleSongsController::class);
        Route::post('interaction/batch/unlike', UnlikeMultipleSongsController::class);

        Route::get('songs/recently-played', FetchRecentlyPlayedSongController::class);
        Route::get('songs/favorite', FetchFavoriteSongsController::class);

        Route::apiResource('playlist-folders', PlaylistFolderController::class);
        Route::apiResource('playlist-folders.playlists', PlaylistFolderPlaylistController::class)->except('destroy');
        Route::delete(
            'playlist-folders/{playlistFolder}/playlists',
            [PlaylistFolderPlaylistController::class, 'destroy']
        );

        // Playlist routes
        Route::apiResource('playlists', PlaylistController::class);
        Route::apiResource('playlists.songs', PlaylistSongController::class)->except('destroy');
        Route::delete('playlists/{playlist}/songs', [PlaylistSongController::class, 'destroy']);
        Route::post('playlists/{playlist}/songs/move', MovePlaylistSongsController::class);

        Route::get('genres/{genre}/songs', GenreSongController::class)->where('genre', '.*');
        Route::get('genres/{genre}/songs/random', FetchRandomSongsInGenreController::class)->where('genre', '.*');
        Route::apiResource('genres', GenreController::class)->where(['genre' => '.*']);

        Route::apiResource('users', UserController::class);

        // User and user profile routes
        Route::apiResource('user', UserController::class);
        Route::get('me', [ProfileController::class, 'show']);
        Route::put('me', [ProfileController::class, 'update']);
        Route::patch('me/preferences', UpdateUserPreferenceController::class);

        // Last.fm-related routes
        Route::post('lastfm/session-key', SetLastfmSessionKeyController::class);
        Route::delete('lastfm/disconnect', DisconnectFromLastfmController::class)->name('lastfm.disconnect');

        // YouTube-related routes
        if (YouTube::enabled()) {
            Route::get('youtube/search/song/{song}', SearchYouTubeController::class);
        }
        
        // General YouTube search route (always available, uses scraping if no API key)
        Route::get('youtube/search', [SearchYouTubeController::class, 'searchByQuery']);

        // SoundCloud-related routes
        Route::get('soundcloud/search', [SearchSoundCloudController::class, 'searchTracks']);
        Route::post('soundcloud/embed', [SearchSoundCloudController::class, 'generateEmbedUrl']);
        Route::get('soundcloud/user', [SearchSoundCloudController::class, 'getUserDetails']);

        // Media information routes
        Route::get('albums/{album}/information', FetchAlbumInformationController::class);
        Route::get('artists/{artist}/information', FetchArtistInformationController::class);

        // Cover/image upload routes
        Route::put('albums/{album}/cover', UploadAlbumCoverController::class);
        Route::get('albums/{album}/thumbnail', FetchAlbumThumbnailController::class);
        Route::put('artists/{artist}/image', UploadArtistImageController::class);
        Route::put('playlists/{playlist}/cover', [PlaylistCoverController::class, 'update']);
        Route::delete('playlists/{playlist}/cover', [PlaylistCoverController::class, 'destroy']);

        // deprecated routes
        Route::put('album/{album}/cover', UploadAlbumCoverController::class);
        Route::get('album/{album}/thumbnail', FetchAlbumThumbnailController::class);
        Route::put('artist/{artist}/image', UploadArtistImageController::class);

        Route::get('search', ExcerptSearchController::class);
        Route::get('search/songs', SongSearchController::class);

        Route::post('invitations', [UserInvitationController::class, 'invite']);
        Route::delete('invitations', [UserInvitationController::class, 'revoke']);

        Route::put('songs/publicize', PublicizeSongsController::class);
        Route::put('songs/privatize', PrivatizeSongsController::class);

        // License routes
        Route::post('licenses/activate', ActivateLicenseController::class);

        // Playlist collaboration routes
        Route::post('playlists/{playlist}/collaborators/invite', CreatePlaylistCollaborationTokenController::class);
        Route::post('playlists/collaborators/accept', AcceptPlaylistCollaborationInviteController::class);
        Route::get('playlists/{playlist}/collaborators', [PlaylistCollaboratorController::class, 'index']);
        Route::delete('playlists/{playlist}/collaborators', [PlaylistCollaboratorController::class, 'destroy']);

        // Podcast routes
        Route::apiResource('podcasts', PodcastController::class);
        Route::get('episodes/{episode}', FetchEpisodeController::class);
        Route::apiResource('podcasts.episodes', PodcastEpisodeController::class);
        Route::delete('podcasts/{podcast}/subscriptions', UnsubscribeFromPodcastController::class);

        // Resource permission routes
        Route::get('permissions/{type}/{id}/{action}', CheckResourcePermissionController::class);

        // Media browser routes
        Route::get('browse/folders', FetchSubfoldersController::class);
        Route::get('browse/songs', PaginateFolderSongsController::class);

        Route::prefix('music-discovery')->name('music-discovery.')->group(static function (): void {
            Route::post('search-seed', [MusicDiscoveryController::class, 'searchSeedTracks'])->name('search-seed');
            Route::post('discover', [MusicDiscoveryController::class, 'discoverMusic'])->name('discover');
            Route::get('track-features/{trackId}', [MusicDiscoveryController::class, 'getTrackFeatures'])->name('track-features');

            Route::post('discover-reccobeats', [MusicDiscoveryController::class, 'discoverMusicReccoBeats'])->name('discover-reccobeats');
            Route::post('discover-rapidapi', [MusicDiscoveryController::class, 'discoverMusicRapidApi'])->name('discover-rapidapi');
            Route::post('batch-track-features', [MusicDiscoveryController::class, 'getBatchTrackFeatures'])->name('batch-track-features');
        });

        // Music Preferences routes (blacklist/save)
        Route::prefix('music-preferences')->name('music-preferences.')->group(static function (): void {
            Route::post('blacklist-track', [MusicPreferencesController::class, 'blacklistTrack'])->name('blacklist-track');
            Route::post('save-track', [MusicPreferencesController::class, 'saveTrack'])->name('save-track');
            Route::post('blacklist-artist', [MusicPreferencesController::class, 'blacklistArtist'])->name('blacklist-artist');
            Route::post('save-artist', [MusicPreferencesController::class, 'saveArtist'])->name('save-artist');
            Route::post('blacklist-unsaved-tracks', [MusicPreferencesController::class, 'blacklistUnsavedTracks'])->name('blacklist-unsaved-tracks');
            
            Route::get('blacklisted-tracks', [MusicPreferencesController::class, 'getBlacklistedTracks'])->name('blacklisted-tracks');
            Route::get('saved-tracks', [MusicPreferencesController::class, 'getSavedTracks'])->name('saved-tracks');
            Route::get('blacklisted-artists', [MusicPreferencesController::class, 'getBlacklistedArtists'])->name('blacklisted-artists');
            Route::get('saved-artists', [MusicPreferencesController::class, 'getSavedArtists'])->name('saved-artists');
            
            Route::delete('blacklist-track', [MusicPreferencesController::class, 'removeFromBlacklist'])->name('remove-blacklist-track');
            Route::delete('blacklist-artist', [MusicPreferencesController::class, 'removeArtistFromBlacklist'])->name('remove-blacklist-artist');
        });
    });

    // Object-storage (S3) routes
    Route::middleware('os.auth')->prefix('os/s3')->group(static function (): void {
        Route::post('song', [S3SongController::class, 'put'])->name('s3.song.put'); // we follow AWS's convention here.
        Route::delete('song', [S3SongController::class, 'remove'])->name('s3.song.remove'); // and here.
    });

    Route::get('demo/credits', FetchDemoCreditsController::class);

    Route::get('debug-reccobeats/{trackId}', function($trackId) {
        try {
            // Test ReccoBeats API directly
            $response = \Illuminate\Support\Facades\Http::get('https://api.reccobeats.com/v1/track/recommendation', [
                'seeds' => $trackId,
                'size' => 3
            ]);
            
            return response()->json([
                'reccobeats_status' => $response->status(),
                'reccobeats_data' => $response->json(),
                'spotify_service_exists' => class_exists(\App\Services\SpotifyService::class),
                'controller_exists' => method_exists(\App\Http\Controllers\API\MusicDiscoveryController::class, 'discoverMusicReccoBeats')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    });
});
