<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $code
 * @property string|null $emoji
 */
class Location extends Model
{
    use HasFactory;

    const NL = 1;
    const RU = 2;

    protected $guarded = false;
    protected $table = 'location';
}
