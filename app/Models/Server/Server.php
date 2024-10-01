<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    const VDSINA = 'vdsina';

    const SERVER_CREATED = 1;
    const SERVER_CONFIGURED = 2;

    protected $guarded = false;
    protected $table = 'server';

    public function isServerCreated()
    {
        return $this->server_status == self::SERVER_CREATED;
    }

    public function isServerConfigured()
    {
        return $this->server_status == self::SERVER_CONFIGURED;
    }
}
