<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesmanPanel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman', function (Blueprint $table) {
            $table->bigInteger('panel_id')->nullable()->after('status')->unsigned();

            $table->index('panel_id', 'panel_salesman_idx');
            $table->foreign('panel_id', 'panel_salesman_fk')
                ->references('id')
                ->on('panel')
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
        //
    }
}
