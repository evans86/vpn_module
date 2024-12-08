<?php

namespace App\Models\KeyActivateUser;

use App\Models\KeyActivate\KeyActivate;
use App\Models\ServerUser\ServerUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $server_user_id
 * @property string|null $key_activate_id
 * @property int|null $location_id
 * @property ServerUser|null $serverUser
 * @property KeyActivate|null $keyActivate
 */
class KeyActivateUser extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'key_activate_user';

    public function serverUser(): BelongsTo
    {
        return $this->belongsTo(ServerUser::class, 'server_user_id', 'id');
    }

    public function keyActivate(): BelongsTo
    {
        return $this->belongsTo(KeyActivate::class, 'key_activate_id', 'id');
    }
}
