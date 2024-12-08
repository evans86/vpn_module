<?php

namespace App\Models\Salesman;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $telegram_id
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 */
class Salesman extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'salesman';
}
