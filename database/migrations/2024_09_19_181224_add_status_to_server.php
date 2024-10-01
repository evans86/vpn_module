<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToServer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('server', function (Blueprint $table) {
            $table->integer('server_status')->nullable()->after('is_free');
            $table->integer('panel_status')->nullable()->after('server_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('server', function (Blueprint $table) {
            //
        });
    }
}
