<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPayments extends Model
{
    use HasFactory;

    protected $table = 'userpayments';

    protected $fillable = [
        'user_id',
        'customer_token',
        'intent_id',
        'status',
    ];
}
