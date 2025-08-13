<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpdateSettingHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'update_setting_history';

    protected $fillable = [
        'updated_by',
        'previous_record',
        'updated_record',
        'updated_time',
        'ipaddress',
        'useragent',
    ];
}
