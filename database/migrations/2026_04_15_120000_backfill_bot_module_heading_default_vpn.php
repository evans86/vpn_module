<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillBotModuleHeadingDefaultVpn extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bot_module') || ! Schema::hasColumn('bot_module', 'heading')) {
            return;
        }
        DB::table('bot_module')->whereNull('heading')->update(['heading' => 'VPN']);
    }

    public function down(): void
    {
        // без отката данных
    }
}
