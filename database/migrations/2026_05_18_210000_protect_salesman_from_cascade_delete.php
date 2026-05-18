<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProtectSalesmanFromCascadeDelete extends Migration
{
    public function up()
    {
        Schema::table('salesman', function (Blueprint $table) {
            if (! Schema::hasColumn('salesman', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('salesman', function (Blueprint $table) {
            $table->dropForeign('panel_salesman_fk');
            $table->foreign('panel_id', 'panel_salesman_fk')
                ->references('id')
                ->on('panel')
                ->nullOnDelete();
        });

        Schema::table('salesman', function (Blueprint $table) {
            $table->dropForeign('module_bot_id_fk');
            $table->foreign('module_bot_id', 'module_bot_id_fk')
                ->references('id')
                ->on('bot_module')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('salesman', function (Blueprint $table) {
            $table->dropForeign('panel_salesman_fk');
            $table->foreign('panel_id', 'panel_salesman_fk')
                ->references('id')
                ->on('panel')
                ->cascadeOnDelete();
        });

        Schema::table('salesman', function (Blueprint $table) {
            $table->dropForeign('module_bot_id_fk');
            $table->foreign('module_bot_id', 'module_bot_id_fk')
                ->references('id')
                ->on('bot_module')
                ->cascadeOnDelete();
        });

        Schema::table('salesman', function (Blueprint $table) {
            if (Schema::hasColumn('salesman', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
}
