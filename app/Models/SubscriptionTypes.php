<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionTypes extends Model
{
    use HasFactory;

    protected $table = 'subscription_types';

    protected $fillable = [
        'name',
        'status',
    ];
}
