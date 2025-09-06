<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesmanKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->UnsignedBigInteger('module_salesman_id')->nullable()->after('pack_salesman_id');
        });

        Schema::table('key_activate', function (Blueprint $table) {
            $table->foreign('module_salesman_id')->references('id')->on('salesman')->onDelete('cascade');

            $table->index('module_salesman_id', 'module_salesman_id_idx');
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
