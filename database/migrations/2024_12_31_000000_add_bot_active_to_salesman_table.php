<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('salesman', function (Blueprint $table) {
            $table->boolean('bot_active')->default(true)->after('bot_link');
        });
    }

    public function down()
    {
        Schema::table('salesman', function (Blueprint $table) {
            $table->dropColumn('bot_active');
        });
    }
};
