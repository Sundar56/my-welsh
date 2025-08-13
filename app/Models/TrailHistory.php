<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrailHistory extends Model
{
    use HasFactory;

    public const TRAIL = '1';

    protected $table = 'trail_history';

    protected $fillable = [
        'user_id',
        'resource_id',
        'trail_start_date',
        'trail_end_date',
        'trail_expired_at',
        'status',
        'expiry_mail',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
