<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Resources\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleResources extends Model
{
    use HasFactory;

    protected $table = 'module_resources';

    protected $fillable = [
        'resource_id',
        'module_name',
        'module_reference',
    ];
}
