<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modules extends Model
{
    use HasFactory;

    public const ADMIN = 0;
    public const TEACHER = 1;
    public const PARENT = 2;

    protected $table = 'modules';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'main_module',
        'sub_module',
        'order_id',
        'type',
        'frontend_slug',
    ];
}
