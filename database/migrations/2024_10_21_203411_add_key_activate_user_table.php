<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeyActivateUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('key_activate_user', function (Blueprint $table) {
            $table->id();
            $table->char('server_user_id', 36)->nullable();
            $table->char('key_activate_id', 36)->nullable();
            $table->bigInteger('location_id')->nullable()->unsigned();
            $table->timestamps();
        });

        Schema::table('key_activate_user', function (Blueprint $table) {
            $table->foreign('server_user_id')->references('id')->on('server_user')->onDelete('cascade');
            $table->foreign('key_activate_id')->references('id')->on('key_activate')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('location')->onDelete('cascade');

            $table->index('server_user_id', 'server_user_id_idx');
            $table->index('key_activate_id', 'key_activate_id_idx');
            $table->index('location_id', 'location_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
