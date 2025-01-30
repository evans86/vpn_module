<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTelegramUserBot extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('telegram_user_salesman', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('salesman_id')->unsigned()->nullable();
            $table->bigInteger('telegram_id')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->integer('status')->nullable();

            $table->index('salesman_id', 'telegram_salesman_idx');
            $table->foreign('salesman_id', 'telegram_salesman_fk')
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
        //
    }
}
