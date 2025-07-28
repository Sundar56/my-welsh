<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeWebhooks extends Model
{
    use HasFactory;

    protected $table = 'stripe_webhooks';

    protected $fillable = [
        'stripe_event_id',
        'stripe_event_type',
        'stripe_request_id',
        'stripe_request_idempotency_key',
        'stripe_api_version',
        'stripe_mode',
        'stripe_object_id',
        'stripe_customer_name',
        'stripe_customer_email',
        'stripe_amount',
        'stripe_currency',
        'stripe_capture_method',
        'stripe_status',
        'stripe_data',
        'webhookstatus',
    ];

    /**
     * Store the webhook history data.
     *
     * @param array $webhookData The data to store in the database.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function storeWebhookHistory(array $webhookData)
    {
        return self::create($webhookData);
    }
}
