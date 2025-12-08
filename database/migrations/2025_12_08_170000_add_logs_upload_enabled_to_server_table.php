<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLogsUploadEnabledToServerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('server', function (Blueprint $table) {
            if (!Schema::hasColumn('server', 'logs_upload_enabled')) {
                $table->boolean('logs_upload_enabled')->default(false)->after('server_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('server', function (Blueprint $table) {
            if (Schema::hasColumn('server', 'logs_upload_enabled')) {
                $table->dropColumn('logs_upload_enabled');
            }
        });
    }
}

