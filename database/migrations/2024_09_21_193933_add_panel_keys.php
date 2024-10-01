<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPanelKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->index('server_id', 'server_user_idx');
            $table->foreign('server_id', 'panel-server_fk')->on('server')->references('id')->onDelete('cascade');
        });

        Schema::table('server', function (Blueprint $table) {
            $table->dropColumn([
                'panel',
                'panel_adress',
                'panel_login',
                'panel_password',
                'panel_key',
                'panel_status',
            ]);
        });

        Schema::table('server_user', function (Blueprint $table) {
            $table->dropForeign('server_user-server_fk');
            $table->dropIndex('server_user_idx');
            $table->dropColumn([
                'server_id',
            ]);
            $table->bigInteger('panel_id')->unsigned()->nullable()->after('id');
            $table->foreign('panel_id')->references('id')->on('panel')->onDelete('cascade');
            $table->index('panel_id', 'panel_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
