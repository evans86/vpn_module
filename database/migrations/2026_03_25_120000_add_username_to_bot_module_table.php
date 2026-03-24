<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUsernameToBotModuleTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('bot_module')) {
            return;
        }
        if (Schema::hasColumn('bot_module', 'username')) {
            return;
        }
        Schema::table('bot_module', function (Blueprint $table) {
            $table->string('username')->nullable()->after('bot_id');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('bot_module') || ! Schema::hasColumn('bot_module', 'username')) {
            return;
        }
        Schema::table('bot_module', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
}
