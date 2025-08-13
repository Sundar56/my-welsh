<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Languages extends Model
{
    use HasFactory;

    public const ENGLISH = 1;
    public const WELSH = 2;

    protected $table = 'languages';

    protected $fillable = [
        'languages',
        'code',
        'status',
    ];
}
