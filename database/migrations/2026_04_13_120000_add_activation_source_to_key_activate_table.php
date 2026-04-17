<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Канал активации: telegram (по умолчанию) | email (внешняя активация без Telegram).
 */
class AddActivationSourceToKeyActivateTable extends Migration
{
    public function up(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->string('activation_source', 32)->default('telegram')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->dropColumn('activation_source');
        });
    }
}
