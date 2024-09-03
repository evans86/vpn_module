<?php

namespace App\Models\ServerUser;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerUser extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'server_user';
}
