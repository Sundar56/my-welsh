<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelHasRoles extends Model
{
    use HasFactory;

    public const ADMIN = 1;
    public const TEACHER = 2;
    public const PARENT = 3;

    protected $table = 'model_has_roles';

    protected $fillable = [
        'role_id',
        'module_type',
        'module_id',
    ];
}
