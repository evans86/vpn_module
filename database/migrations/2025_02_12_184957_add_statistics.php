<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatistics extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('server_monitoring', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('panel_id')->nullable()->unsigned();
            $table->text('statistics')->nullable();
            $table->timestamps();

            $table->index('panel_id', 'panel_monitoring_idx');
            $table->foreign('panel_id', 'panel_monitoring_fk')
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
