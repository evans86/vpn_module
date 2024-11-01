<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeyActivateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('key_activate', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('traffic_limit')->nullable();
            $table->bigInteger('pack_salesman_id')->nullable()->unsigned();
            $table->integer('finish_at')->nullable();
            $table->bigInteger('user_tg_id')->nullable();
            $table->integer('deleted_at')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        Schema::table('key_activate', function (Blueprint $table) {
            $table->foreign('pack_salesman_id')->references('id')->on('pack_salesman')->onDelete('cascade');

            $table->index('pack_salesman_id', 'pack_salesman_id_idx');
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
