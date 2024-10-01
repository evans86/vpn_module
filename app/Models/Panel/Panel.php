<?php

namespace App\Models\Panel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Panel extends Model
{
    use HasFactory;

    const MARZBAN = 'marzban';

    const PANEL_CREATED = 1;
    const PANEL_CONFIGURED = 2;

    protected $guarded = false;
    protected $table = 'panel';

    public function isPanelCreated()
    {
        return $this->panel_status == self::PANEL_CREATED;
    }

    public function isPanelConfigured()
    {
        return $this->panel_status == self::PANEL_CONFIGURED;
    }
}
