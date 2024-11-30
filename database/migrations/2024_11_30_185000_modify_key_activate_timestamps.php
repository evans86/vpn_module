<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('key_activate', function (Blueprint $table) {
            // Сначала изменим тип на bigInteger для больших значений timestamp
            $table->bigInteger('finish_at')->nullable()->change();
            $table->bigInteger('deleted_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->integer('finish_at')->nullable()->change();
            $table->integer('deleted_at')->nullable()->change();
        });
    }
};
