<?php

declare(strict_types=1);

namespace App\Api\Admin\Modules\Adminlogin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminLoginHistory extends Model
{
    use HasFactory;

    protected $table = 'adminloginhistory';

    protected $fillable = [
        'user_id',
        'logintime',
        'logouttime',
        'duration',
        'ipaddress',
        'useragent',
    ];
}
