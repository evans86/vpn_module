<?php

namespace App\Models\KeyProtocols;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeyProtocols extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'key_protocols';
}
