<?php

declare(strict_types=1);

namespace App\Api\Parent\Modules\Playlists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentInvite extends Model
{
    use HasFactory;

    protected $table = 'parent_invites';

    protected $fillable = [
        'playlist_id',
        'parent_email',
        'is_invited',
        'status',
    ];
}
