<?php

namespace App\Models\ServerUser;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use phpseclib\Math\BigInteger;

/**
 * @property int $id
 * @property BigInteger|null $panel_id
 * @property string|null $user_id
 * @property bool|null $is_free
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServerUser extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'server_user';
}
