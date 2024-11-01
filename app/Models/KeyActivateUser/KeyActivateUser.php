<?php

namespace App\Models\KeyActivateUser;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $server_user_id
 * @property string|null $key_activate_id
 * @property int|null $location_id
 */
class KeyActivateUser extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'key_activate_user';
}
