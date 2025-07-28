<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Playlists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaylistResource extends Model
{
    use HasFactory;

    protected $table = 'playlist_resources';

    protected $fillable = [
        'playlist_id',
        'module_resource_topic_id',
        'position',
    ];
}
