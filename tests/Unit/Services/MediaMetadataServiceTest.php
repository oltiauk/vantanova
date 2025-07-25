<?php

namespace Tests\Unit\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Services\ImageWriter;
use App\Services\MediaMetadataService;
use App\Services\SpotifyService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class MediaMetadataServiceTest extends TestCase
{
    private SpotifyService|MockInterface $spotifyService;
    private ImageWriter|MockInterface $imageWriter;
    private Finder|MockInterface $finder;
    private MediaMetadataService $mediaMetadataService;

    public function setUp(): void
    {
        parent::setUp();

        $this->spotifyService = Mockery::mock(SpotifyService::class);
        $this->imageWriter = Mockery::mock(ImageWriter::class);
        $this->finder = Mockery::mock(Finder::class);

        $this->mediaMetadataService = new MediaMetadataService(
            $this->spotifyService,
            $this->imageWriter,
            $this->finder,
        );
    }

    #[Test]
    public function tryDownloadAlbumCover(): void
    {
        /** @var Album $album */
        $album = Album::factory()->create(['cover' => '']);

        $this->spotifyService
            ->shouldReceive('tryGetAlbumCover')
            ->with($album)
            ->andReturn('/dev/null/cover.jpg');

        $this->imageWriter->shouldReceive('write')->twice(); // once for the cover, once for the thumbnail

        $this->mediaMetadataService->tryDownloadAlbumCover($album);
    }

    #[Test]
    public function writeAlbumCover(): void
    {
        /** @var Album $album */
        $album = Album::factory()->create();
        $coverPath = '/koel/public/img/album/foo.jpg';

        $this->imageWriter
            ->shouldReceive('write')
            ->once()
            ->with('/koel/public/img/album/foo.jpg', 'dummy-src');

        $this->imageWriter->shouldReceive('write')->once();

        $this->mediaMetadataService->writeAlbumCover($album, 'dummy-src', $coverPath);
        self::assertSame(album_cover_url('foo.jpg'), $album->refresh()->cover);
    }

    #[Test]
    public function tryDownloadArtistImage(): void
    {
        /** @var Artist $artist */
        $artist = Artist::factory()->create(['image' => '']);

        $this->spotifyService
            ->shouldReceive('tryGetArtistImage')
            ->with($artist)
            ->andReturn('/dev/null/img.jpg');

        $this->imageWriter->shouldReceive('write')->once();

        $this->mediaMetadataService->tryDownloadArtistImage($artist);
    }

    #[Test]
    public function writeArtistImage(): void
    {
        /** @var Artist $artist */
        $artist = Artist::factory()->create();
        $imagePath = '/koel/public/img/artist/foo.jpg';

        $this->imageWriter
            ->shouldReceive('write')
            ->once()
            ->with('/koel/public/img/artist/foo.jpg', 'dummy-src');

        $this->mediaMetadataService->writeArtistImage($artist, 'dummy-src', $imagePath);

        self::assertSame(artist_image_url('foo.jpg'), $artist->refresh()->image);
    }
}
