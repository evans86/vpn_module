<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackOrderSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pack_order_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pack_id')->unsigned()->nullable();
            $table->boolean('is_available')->default(true); // Доступен ли пакет для заказа
            $table->integer('sort_order')->default(0); // Порядок отображения
            $table->timestamps();

            $table->unique('pack_id', 'pack_order_settings_pack_unique');
            $table->index('is_available', 'pack_order_settings_available_idx');
            $table->index('sort_order', 'pack_order_settings_sort_idx');

            $table->foreign('pack_id', 'pack_order_settings_pack_fk')
                  ->references('id')
                  ->on('pack')
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
        Schema::dropIfExists('pack_order_settings');
    }
}

