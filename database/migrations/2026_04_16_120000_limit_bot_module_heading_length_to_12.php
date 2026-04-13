<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LimitBotModuleHeadingLengthTo12 extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bot_module') || ! Schema::hasColumn('bot_module', 'heading')) {
            return;
        }

        foreach (DB::table('bot_module')->select('id', 'heading')->get() as $row) {
            $h = $row->heading;
            if ($h === null) {
                continue;
            }
            $trimmed = mb_substr((string) $h, 0, 12);
            if ($trimmed !== $h) {
                DB::table('bot_module')->where('id', $row->id)->update(['heading' => $trimmed]);
            }
        }

        Schema::table('bot_module', function (Blueprint $table) {
            $table->string('heading', 12)->default('VPN')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bot_module') || ! Schema::hasColumn('bot_module', 'heading')) {
            return;
        }
        Schema::table('bot_module', function (Blueprint $table) {
            $table->string('heading', 5000)->default('VPN')->change();
        });
    }
}
