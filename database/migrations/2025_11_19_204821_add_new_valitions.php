<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewValitions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('connection_limit_violations', function (Blueprint $table) {
            // Добавляем поля для учета уведомлений
            $table->integer('notifications_sent')->default(0)->after('violation_count');
            $table->timestamp('last_notification_sent_at')->nullable()->after('notifications_sent');

            // Добавляем поля для замены ключа
            $table->timestamp('key_replaced_at')->nullable()->after('resolved_at');
            $table->string('replaced_key_id')->nullable()->after('key_replaced_at');

            // Добавляем индекс для быстрого поиска
            $table->index(['status', 'violation_count']);
            $table->index('last_notification_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('connection_limit_violations', function (Blueprint $table) {
            $table->dropColumn([
                'notifications_sent',
                'last_notification_sent_at',
                'key_replaced_at',
                'replaced_key_id'
            ]);

            $table->dropIndex(['status', 'violation_count']);
            $table->dropIndex(['last_notification_sent_at']);
        });
    }
}
