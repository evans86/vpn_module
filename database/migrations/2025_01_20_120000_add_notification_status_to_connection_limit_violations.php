<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNotificationStatusToConnectionLimitViolations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('connection_limit_violations', function (Blueprint $table) {
            // Статус последней отправки уведомления
            if (!Schema::hasColumn('connection_limit_violations', 'last_notification_status')) {
                $table->string('last_notification_status')->nullable()->after('last_notification_sent_at');
            }
            
            // Текст последней ошибки отправки
            if (!Schema::hasColumn('connection_limit_violations', 'last_notification_error')) {
                $table->text('last_notification_error')->nullable()->after('last_notification_status');
            }
            
            // Счетчик попыток отправки при технических ошибках
            if (!Schema::hasColumn('connection_limit_violations', 'notification_retry_count')) {
                $table->integer('notification_retry_count')->default(0)->after('last_notification_error');
            }
        });
        
        // Индекс создаем отдельно, проверяя его существование
        $indexExists = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'connection_limit_violations' 
            AND index_name = 'clv_status_notif_status_idx'
        ");
        
        if ($indexExists[0]->count == 0) {
            Schema::table('connection_limit_violations', function (Blueprint $table) {
                $table->index(['status', 'last_notification_status'], 'clv_status_notif_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('connection_limit_violations', function (Blueprint $table) {
            $table->dropIndex('clv_status_notif_status_idx');
            $table->dropColumn([
                'last_notification_status',
                'last_notification_error',
                'notification_retry_count'
            ]);
        });
    }
}

