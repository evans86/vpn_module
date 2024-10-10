<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $provider_id
 * @property string|null $ip
 * @property string|null $login
 * @property string|null $password
 * @property string|null $name
 * @property string|null $dns_record_id
 * @property string|null $host
 * @property string|null $provider
 * @property int|null $location_id
 * @property int|null $server_status
 * @property bool|null $is_free
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Server extends Model
{
    use HasFactory;

    const VDSINA = 'vdsina';

    const SERVER_CREATED = 1;
    const SERVER_CONFIGURED = 2;
    const PASSWORD_UPDATE = 3;

    protected $guarded = false;
    protected $table = 'server';
}
