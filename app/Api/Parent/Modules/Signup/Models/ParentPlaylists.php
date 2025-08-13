<?php

declare(strict_types=1);

namespace App\Api\Parent\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentPlaylists extends Model
{
    use HasFactory;

    protected $table = 'parent_playlists';

    protected $fillable = [
        'parent_id',
        'playlist_id',
        'status',
    ];
}
