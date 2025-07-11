<?php

namespace App\Models;

use App\Builders\SongBuilder;
use App\Casts\Podcast\EpisodeMetadataCast;
use App\Casts\SongLyricsCast;
use App\Casts\SongStorageCast;
use App\Casts\SongTitleCast;
use App\Enums\PlayableType;
use App\Enums\SongStorageType;
use App\Models\Concerns\SupportsDeleteWhereValueNotIn;
use App\Values\SongStorageMetadata\DropboxMetadata;
use App\Values\SongStorageMetadata\LocalMetadata;
use App\Values\SongStorageMetadata\S3CompatibleMetadata;
use App\Values\SongStorageMetadata\S3LambdaMetadata;
use App\Values\SongStorageMetadata\SftpMetadata;
use App\Values\SongStorageMetadata\SongStorageMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use PhanAn\Poddle\Values\EpisodeMetadata;
use Throwable;
use Webmozart\Assert\Assert;

/**
 * @property string $path
 * @property string $title
 * @property ?Album $album
 * @property ?Artist $artist
 * @property ?Artist $album_artist
 * @property float $length
 * @property string $lyrics
 * @property int $track
 * @property int $disc
 * @property int $album_id
 * @property int|null $year
 * @property string $genre
 * @property ?string $mime_type The MIME type of the song file, if available
 * @property string $id
 * @property int $artist_id
 * @property int $mtime
 * @property ?bool $liked Whether the song is liked by the current user (dynamically calculated)
 * @property ?int $play_count The number of times the song has been played by the current user (dynamically calculated)
 * @property Carbon $created_at
 * @property int $owner_id
 * @property bool $is_public
 * @property User $owner
 * @property-read SongStorageMetadata $storage_metadata
 * @property SongStorageType $storage
 * @property ?string $folder_id
 * @property ?Folder $folder
 * @property ?string $basename
 *
 * // The following are only available for collaborative playlists
 * @property-read ?string $collaborator_email The email of the user who added the song to the playlist
 * @property-read ?string $collaborator_name The name of the user who added the song to the playlist
 * @property-read ?string $collaborator_avatar The avatar of the user who added the song to the playlist
 * @property-read ?string $collaborator_public_id The public ID of the user who added the song to the playlist
 * @property-read ?string $added_at The date the song was added to the playlist
 * @property-read PlayableType $type
 *
 * // Podcast episode properties
 * @property ?EpisodeMetadata $episode_metadata
 * @property ?string $episode_guid
 * @property ?string $podcast_id
 * @property ?Podcast $podcast
 */
class Song extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;
    use HasUuids;
    use Searchable;
    use SupportsDeleteWhereValueNotIn;

    protected $guarded = [];
    protected $hidden = ['updated_at', 'path', 'mtime'];

    protected $casts = [
        'title' => SongTitleCast::class,
        'lyrics' => SongLyricsCast::class,
        'length' => 'float',
        'mtime' => 'int',
        'track' => 'int',
        'disc' => 'int',
        'is_public' => 'bool',
        'storage' => SongStorageCast::class,
        'episode_metadata' => EpisodeMetadataCast::class,
    ];

    protected $with = ['album', 'artist', 'podcast', 'owner'];

    public static function query(?PlayableType $type = null, ?User $user = null): SongBuilder
    {
        return parent::query()
            ->when($type, static fn (Builder $query) => match ($type) { // @phpstan-ignore-line phpcs:ignore
                PlayableType::SONG => $query->whereNull('songs.podcast_id'),
                PlayableType::PODCAST_EPISODE => $query->whereNotNull('songs.podcast_id'),
                default => $query,
            })
            ->when($user, static fn (SongBuilder $query) => $query->forUser($user)); // @phpstan-ignore-line
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newEloquentBuilder($query): SongBuilder
    {
        return new SongBuilder($query);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    protected function albumArtist(): Attribute
    {
        return Attribute::get(fn () => $this->album?->artist)->shouldCache();
    }

    protected function type(): Attribute
    {
        return Attribute::get(fn () => $this->podcast_id ? PlayableType::PODCAST_EPISODE : PlayableType::SONG);
    }

    public function accessibleBy(User $user): bool
    {
        if ($this->isEpisode()) {
            return $user->subscribedToPodcast($this->podcast);
        }

        return $this->is_public || $this->ownedBy($user);
    }

    public function ownedBy(User $user): bool
    {
        return $this->owner->id === $user->id;
    }

    protected function storageMetadata(): Attribute
    {
        return (new Attribute(
            get: function (): SongStorageMetadata {
                try {
                    switch ($this->storage) {
                        case SongStorageType::SFTP:
                            preg_match('/^sftp:\\/\\/(.*)/', $this->path, $matches);
                            return SftpMetadata::make($matches[1]);

                        case SongStorageType::S3:
                            preg_match('/^s3:\\/\\/(.*)\\/(.*)/', $this->path, $matches);
                            return S3CompatibleMetadata::make($matches[1], $matches[2]);

                        case SongStorageType::S3_LAMBDA:
                            preg_match('/^s3:\\/\\/(.*)\\/(.*)/', $this->path, $matches);
                            return S3LambdaMetadata::make($matches[1], $matches[2]);

                        case SongStorageType::DROPBOX:
                            preg_match('/^dropbox:\\/\\/(.*)/', $this->path, $matches);
                            return DropboxMetadata::make($matches[1]);

                        default:
                            return LocalMetadata::make($this->path);
                    }
                } catch (Throwable) {
                    return LocalMetadata::make($this->path);
                }
            }
        ))->shouldCache();
    }

    protected function basename(): Attribute
    {
        return Attribute::get(function () {
            Assert::eq($this->type, PlayableType::SONG);

            return File::basename($this->path);
        });
    }

    public static function getPathFromS3BucketAndKey(string $bucket, string $key): string
    {
        return "s3://$bucket/$key";
    }

    /** @inheritdoc */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
        ];

        if ($this->episode_metadata?->description) {
            $array['episode_description'] = $this->episode_metadata->description;
        }

        if ($this->artist && !$this->artist->is_unknown && !$this->artist->is_various) {
            $array['artist'] = $this->artist->name;
        }

        return $array;
    }

    public function isEpisode(): bool
    {
        return $this->type === PlayableType::PODCAST_EPISODE;
    }

    public function isStoredOnCloud(): bool
    {
        return in_array($this->storage, [
            SongStorageType::S3,
            SongStorageType::S3_LAMBDA,
            SongStorageType::DROPBOX,
        ], true);
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
