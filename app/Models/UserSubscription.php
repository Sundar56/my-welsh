<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    public const STATUS_ZERO = 0;
    public const STATUS_ONE = 1;
    public const CARD = '0';
    public const BACS = '1';
    public const PARENT = '2';

    protected $table = 'user_subscription';

    protected $fillable = [
        'user_id',
        'resource_id',
        'status',
        'latest_subscription',
    ];
}
