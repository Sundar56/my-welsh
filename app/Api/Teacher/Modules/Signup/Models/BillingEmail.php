<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'billing_emails';

    protected $fillable = [
        'invoice_email',
        'invoice_path',
        'invoice_sent',
        'is_paid',
    ];
}
