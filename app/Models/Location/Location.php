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

    protected $guarded = false;
    protected $table = 'location';
}
