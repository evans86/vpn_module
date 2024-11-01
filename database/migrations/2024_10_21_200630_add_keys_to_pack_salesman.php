<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeysToPackSalesman extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pack_salesman', function (Blueprint $table) {
            $table->foreign('pack_id')->references('id')->on('pack')->onDelete('cascade');
            $table->foreign('salesman_id')->references('id')->on('salesman')->onDelete('cascade');

            $table->index('pack_id', 'pack_id_idx');
            $table->index('salesman_id', 'salesman_id_idx');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pack_salesman', function (Blueprint $table) {
            //
        });
    }
}
