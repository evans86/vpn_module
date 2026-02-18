<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            // Увеличиваем размер полей для зашифрованных данных
            // Зашифрованные данные могут быть в 2-3 раза длиннее оригинальных
            // Используем text() вместо string() для поддержки длинных зашифрованных значений
            $table->text('panel_password')->nullable()->change();
            $table->text('auth_token')->nullable()->change();
            // reality_private_key уже text() из предыдущей миграции
            $table->text('reality_public_key')->nullable()->change();
            $table->text('reality_short_id')->nullable()->change();
            $table->text('reality_grpc_short_id')->nullable()->change();
            $table->text('reality_xhttp_short_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            // Возвращаем обратно к string (но это может вызвать проблемы с зашифрованными данными)
            $table->string('panel_password')->nullable()->change();
            $table->string('auth_token')->nullable()->change();
            $table->string('reality_private_key')->nullable()->change();
            $table->string('reality_public_key')->nullable()->change();
            $table->string('reality_short_id')->nullable()->change();
            $table->string('reality_grpc_short_id')->nullable()->change();
            $table->string('reality_xhttp_short_id')->nullable()->change();
        });
    }
};

