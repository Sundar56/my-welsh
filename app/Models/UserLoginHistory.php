<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginHistory extends Model
{
    use HasFactory;

    protected $table = 'userloginhistory';

    protected $fillable = [
        'user_id',
        'logintime',
        'logouttime',
        'duration',
        'ipaddress',
        'useragent',
    ];
}
