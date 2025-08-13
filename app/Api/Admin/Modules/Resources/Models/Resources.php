<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Resources\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resources extends Model
{
    use HasFactory;

    public const DEFAULT = '0';

    protected $table = 'learning_resources';

    protected $fillable = [
        'resource_name',
        'monthly_fee',
        'annual_fee',
        'resource_reference',
        'type',
    ];
}
