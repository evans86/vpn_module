<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToApplicationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_logs', function (Blueprint $table) {
//            $table->index('level');
//            $table->index('source');
//            $table->index('user_id');
//            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_logs', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropIndex(['source']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });
    }
}
