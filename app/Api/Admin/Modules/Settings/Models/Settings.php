<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settings extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'settings';

    protected $fillable = [
        'user_id',
        'apikey',
        'apisecret',
        'webhookkey',
        'webhookurl',
        'fixedfee',
        'percentagefee',
        'title',
        'description',
        'keyword',
        'logo',
    ];
}
