<?php

namespace App\Models\PackSalesman;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $pack_id
 * @property int|null $salesman_id
 * @property int|null $status
 */
class PackSalesman extends Model
{
    const NOT_PAID = 0;
    const PAID = 1;
    const EXPIRED = 2;

    use HasFactory;

    protected $guarded = false;
    protected $table = 'pack_salesman';
}
