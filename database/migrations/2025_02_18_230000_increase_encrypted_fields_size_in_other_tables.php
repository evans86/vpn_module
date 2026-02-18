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
        // Увеличиваем размер полей для зашифрованных данных в таблице server
        if (Schema::hasTable('server')) {
            Schema::table('server', function (Blueprint $table) {
                $table->text('password')->nullable()->change();
                $table->text('login')->nullable()->change();
            });
        }

        // Увеличиваем размер полей для зашифрованных данных в таблице salesman
        if (Schema::hasTable('salesman')) {
            Schema::table('salesman', function (Blueprint $table) {
                $table->text('token')->nullable()->change();
            });
        }

        // Увеличиваем размер полей для зашифрованных данных в таблице bot_module
        if (Schema::hasTable('bot_module')) {
            Schema::table('bot_module', function (Blueprint $table) {
                $table->text('private_key')->nullable()->change();
                $table->text('public_key')->nullable()->change();
                $table->text('secret_user_key')->nullable()->change();
            });
        }

        // Увеличиваем размер полей для зашифрованных данных в таблице server_user
        if (Schema::hasTable('server_user')) {
            Schema::table('server_user', function (Blueprint $table) {
                $table->text('keys')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем обратно к string (но это может вызвать проблемы с зашифрованными данными)
        if (Schema::hasTable('server')) {
            Schema::table('server', function (Blueprint $table) {
                $table->string('password')->nullable()->change();
                $table->string('login')->nullable()->change();
            });
        }

        if (Schema::hasTable('salesman')) {
            Schema::table('salesman', function (Blueprint $table) {
                $table->string('token')->nullable()->change();
            });
        }

        if (Schema::hasTable('bot_module')) {
            Schema::table('bot_module', function (Blueprint $table) {
                $table->string('private_key')->nullable()->change();
                $table->string('public_key')->nullable()->change();
                $table->string('secret_user_key')->nullable()->change();
            });
        }

        if (Schema::hasTable('server_user')) {
            Schema::table('server_user', function (Blueprint $table) {
                $table->string('keys')->nullable()->change();
            });
        }
    }
};

