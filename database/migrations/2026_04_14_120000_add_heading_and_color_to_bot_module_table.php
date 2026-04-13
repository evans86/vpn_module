<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHeadingAndColorToBotModuleTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bot_module')) {
            return;
        }
        Schema::table('bot_module', function (Blueprint $table) {
            if (! Schema::hasColumn('bot_module', 'heading')) {
                $table->string('heading', 12)->default('VPN');
            }
            if (! Schema::hasColumn('bot_module', 'color')) {
                $table->unsignedTinyInteger('color')->default(1);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bot_module')) {
            return;
        }
        Schema::table('bot_module', function (Blueprint $table) {
            if (Schema::hasColumn('bot_module', 'heading')) {
                $table->dropColumn('heading');
            }
            if (Schema::hasColumn('bot_module', 'color')) {
                $table->dropColumn('color');
            }
        });
    }
}
