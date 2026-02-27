<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Добавить локацию Финляндия (FI).
     */
    public function up(): void
    {
        if (DB::table('location')->where('code', 'FI')->exists()) {
            return;
        }
        DB::table('location')->insert([
            'id' => 3,
            'code' => 'FI',
            'emoji' => ':fi:',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('location')->where('code', 'FI')->delete();
    }
};
