<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeApiHistory extends Model
{
    use HasFactory;

    protected $table = 'stripe_apihistory';

    protected $fillable = [
        'request_id',
        'livemode',
        'type',
        'method',
        'status',
        'request_data',
        'response_data',
        'stripe_fee',
        'amount',
        'currency',
        'description',
        'user_agent',
        'ip',
        'user_id',
        'customer_token',
    ];
}
