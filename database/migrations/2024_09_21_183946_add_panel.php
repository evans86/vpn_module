<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPanel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('panel', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('server_id')->nullable();
            $table->string('panel')->nullable();
            $table->string('panel_adress')->nullable();
            $table->string('panel_login')->nullable();
            $table->string('panel_password')->nullable();
            $table->string('panel_key')->nullable();
            $table->string('panel_status')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('token_died_time')->nullable();
            $table->timestamps();
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
