<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePanelMonthlyStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('panel_monthly_statistics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('panel_id')->unsigned();
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->integer('active_users')->nullable();
            $table->integer('online_users')->nullable();
            $table->bigInteger('traffic_used_bytes')->nullable();
            $table->bigInteger('traffic_limit_bytes')->nullable();
            $table->decimal('traffic_used_percent', 5, 2)->nullable();
            $table->timestamps();

            // Уникальный индекс для панели + год + месяц
            $table->unique(['panel_id', 'year', 'month'], 'panel_monthly_statistics_unique');
            
            // Индекс для быстрого поиска по панели
            $table->index('panel_id', 'panel_monthly_statistics_panel_idx');
            
            // Индекс для поиска по году и месяцу
            $table->index(['year', 'month'], 'panel_monthly_statistics_period_idx');
            
            // Внешний ключ
            $table->foreign('panel_id', 'panel_monthly_statistics_panel_fk')
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
        Schema::dropIfExists('panel_monthly_statistics');
    }
}
