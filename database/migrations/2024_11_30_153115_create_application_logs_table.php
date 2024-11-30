<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplicationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('application_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->index()->charset('utf8mb4')->collate('utf8mb4_unicode_ci');  // info, error, warning, critical, debug
            $table->string('source')->index()->charset('utf8mb4')->collate('utf8mb4_unicode_ci'); // модуль или компонент, откуда пришел лог
            $table->text('message')->charset('utf8mb4')->collate('utf8mb4_unicode_ci');           // сообщение лога
            $table->json('context')->nullable(); // дополнительные данные в формате JSON
            $table->string('user_id')->nullable()->index(); // ID пользователя, если применимо
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable()->charset('utf8mb4')->collate('utf8mb4_unicode_ci');
            $table->timestamps();

            // Индекс для быстрой очистки старых логов
            $table->index('created_at', 'idx_logs_cleanup');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('application_logs');
    }
}
