<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Локация Турция (TR).
     */
    public function up(): void
    {
        if (DB::table('location')->where('code', 'TR')->exists()) {
            return;
        }
        DB::table('location')->insert([
            'id' => 4,
            'code' => 'TR',
            'emoji' => ':tr:',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('location')->where('code', 'TR')->delete();
    }
};
