<?php

namespace App\Models\Server;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetTerritoryReport extends Model
{
    protected $table = 'fleet_territory_reports';

    protected $fillable = [
        'user_id',
        'submitter_note',
        'mode_label',
        'country_code',
        'country_name',
        'region',
        'city',
        'isp',
        'asn',
        'public_ip',
        'geo_service',
        'geo_parse_error',
        'raw_report',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Краткая подпись для списков.
     */
    public function territoryLabel(): string
    {
        $parts = array_filter([
            trim((string) $this->country_name),
            trim((string) $this->city),
        ]);

        return $parts ? implode(', ', $parts) : '—';
    }
}
