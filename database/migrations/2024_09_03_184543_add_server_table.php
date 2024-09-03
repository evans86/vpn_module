<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('server', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id')->nullable();
            $table->integer('ip')->nullable();
            $table->string('login')->nullable();
            $table->string('password')->nullable();
            $table->string('name')->nullable();
            $table->string('host')->nullable();
            $table->string('provider')->nullable();
            $table->string('panel')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('panel_adress')->nullable();
            $table->string('panel_login')->nullable();
            $table->string('panel_password')->nullable();
            $table->string('panel_key')->nullable();
            $table->boolean('is_free')->nullable();
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
        Schema::dropIfExists('server');
    }
}
