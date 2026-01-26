<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateOrderSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Ключ настройки
            $table->text('value')->nullable(); // Значение настройки
            $table->timestamps();

            $table->index('key', 'order_settings_key_idx');
        });

        // Вставляем начальные настройки
        DB::table('order_settings')->insert([
            ['key' => 'system_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'notification_telegram_id', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_settings');
    }
}

