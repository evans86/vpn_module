<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Почта для уведомления об активации ключа (заготовка под отправку письма).
 */
class AddEmailToKeyActivateTable extends Migration
{
    public function up(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('user_tg_id');
        });
    }

    public function down(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
}
