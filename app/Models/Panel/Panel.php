<?php

namespace App\Models\Panel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $server_id
 * @property string|null $panel
 * @property string|null $panel_adress
 * @property string|null $panel_login
 * @property string|null $panel_password
 * @property string|null $panel_status
 * @property string|null $auth_token
 * @property int|null $token_died_time
 */
class Panel extends Model
{
    use HasFactory;

    const MARZBAN = 'marzban';

    const PANEL_CREATED = 1;
    const PANEL_CONFIGURED = 2;

    protected $guarded = false;
    protected $table = 'panel';
}
