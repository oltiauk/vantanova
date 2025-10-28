<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListenedTrack extends Model
{
    protected $fillable = [
        'user_id',
        'track_key',
        'track_name',
        'artist_name',
        'spotify_id',
        'isrc',
        'first_listened_at',
        'last_listened_at',
    ];

    protected $casts = [
        'first_listened_at' => 'datetime',
        'last_listened_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}



