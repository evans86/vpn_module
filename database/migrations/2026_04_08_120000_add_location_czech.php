<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Локация Чехия (CZ).
     */
    public function up(): void
    {
        if (DB::table('location')->where('code', 'CZ')->exists()) {
            return;
        }
        DB::table('location')->insert([
            'id' => 5,
            'code' => 'CZ',
            'emoji' => ':cz:',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('location')->where('code', 'CZ')->delete();
    }
};
