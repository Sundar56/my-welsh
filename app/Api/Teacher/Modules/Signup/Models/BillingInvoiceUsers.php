<?php

declare(strict_types=1);

namespace App\Api\Teacher\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingInvoiceUsers extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'billing_invoice_users';

    protected $fillable = [
        'billing_invoice_id',
        'user_id',
    ];
}
