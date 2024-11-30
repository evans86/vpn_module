<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPackSalesmanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pack_salesman', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pack_id')->unsigned()->nullable();
            $table->bigInteger('salesman_id')->unsigned()->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();

            $table->index('pack_id', 'pack_salesman_pack_idx');
            $table->index('salesman_id', 'pack_salesman_salesman_idx');

            $table->foreign('pack_id', 'pack_salesman_pack_fk')
                  ->references('id')
                  ->on('pack')
                  ->onDelete('cascade');

            $table->foreign('salesman_id', 'pack_salesman_salesman_fk')
                  ->references('id')
                  ->on('salesman')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pack_salesman');
    }
}
