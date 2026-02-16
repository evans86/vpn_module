<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApiIdToPackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pack', function (Blueprint $table) {
            $table->integer('api_id')->nullable()->after('id')->comment('API ID для интеграции с BOT-T');
            $table->index('api_id', 'pack_api_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pack', function (Blueprint $table) {
            $table->dropIndex('pack_api_id_idx');
            $table->dropColumn('api_id');
        });
    }
}

