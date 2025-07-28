<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Playlists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    protected $table = 'playlists';

    protected $fillable = [
        'user_id',
        'resource_id',
        'playlist_name',
        'is_shared',
        'playlist_reference',
        'created_by',
    ];
}
