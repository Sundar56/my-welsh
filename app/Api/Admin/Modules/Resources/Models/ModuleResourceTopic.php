<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Resources\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleResourceTopic extends Model
{
    use HasFactory;

    protected $table = 'module_resource_topics';

    protected $fillable = [
        'module_resource_id',
        'resource_topic',
        'resource_type',
        'description',
        'resource_path',
        'video_url',
    ];
}
