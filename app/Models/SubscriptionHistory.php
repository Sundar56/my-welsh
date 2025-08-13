<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionHistory extends Model
{
    use HasFactory;

    public const MONTH_FEE = '1';
    public const ANNUAL_FEE = '2';

    protected $table = 'subscription_history';

    protected $fillable = [
        'type_id',
        'subscription_amount',
        'subscription_start_date',
        'subscription_end_date',
        'subscription_duration',
        'fee_type',
        'expiry_mail',
    ];
}
