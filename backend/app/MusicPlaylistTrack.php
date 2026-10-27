<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicPlaylistTrack extends Model
{
    use HasFactory;

    protected $table = 'music_playlist_tracks';

    protected $fillable = [
        'playlist_id',
        'track_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(MusicPlaylist::class, 'playlist_id', 'id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'track_id', 'id');
    }
}
