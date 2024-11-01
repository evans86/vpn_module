<?php

namespace App\Models\ServerUser;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use phpseclib\Math\BigInteger;

/**
 * @property string $id
 * @property BigInteger|null $panel_id
 * @property string|null $keys
 * @property bool|null $is_free
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServerUser extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'server_user';
}
