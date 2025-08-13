<?php

declare(strict_types=1);

namespace App\Api\Parent\Modules\Signup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherInvites extends Model
{
    use HasFactory;

    protected $table = 'teacher_invites';

    protected $fillable = [
        'teacher_id',
        'parent_id',
        'status',
    ];
}
