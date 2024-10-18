<?php

namespace App\Models\KeyProtocols;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use phpseclib\Math\BigInteger;

/**
 * @property int $id
 * @property string|null $user_id
 * @property string|null $key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KeyProtocols extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'key_protocols';
}
