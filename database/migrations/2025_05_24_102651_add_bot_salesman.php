<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBotSalesman extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('salesman', function (Blueprint $table) {
            $table->bigInteger('module_bot_id')->nullable()->after('bot_link')->unsigned();

            $table->index('module_bot_id', 'salesman_bot_idx');
            $table->foreign('module_bot_id', 'module_bot_id_fk')
                ->references('id')
                ->on('bot_module')
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
