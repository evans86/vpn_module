<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('server', function (Blueprint $table) {
            $table->dropColumn('location_id');
        });
        Schema::table('server_user', function (Blueprint $table) {
            $table->dropColumn('server_id');
        });
        Schema::table('key_protocols', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('server', function (Blueprint $table) {
            $table->bigInteger('location_id')->unsigned()->nullable()->after('panel');
            $table->index('location_id', 'server_location_idx');
            $table->foreign('location_id', 'server_location-location_fk')->on('location')->references('id')->onDelete('cascade');
        });

        Schema::table('server_user', function (Blueprint $table) {
            $table->bigInteger('server_id')->unsigned()->nullable()->after('id');
            $table->index('server_id', 'server_user_idx');
            $table->foreign('server_id', 'server_user-server_fk')->on('server')->references('id')->onDelete('cascade');
        });

        Schema::table('key_protocols', function (Blueprint $table) {
            $table->string('user_id')->nullable()->after('id');
            $table->index('user_id', 'key_protocols_idx');
            $table->foreign('user_id', 'key_protocols-user_fk')->on('server_user')->references('id')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
