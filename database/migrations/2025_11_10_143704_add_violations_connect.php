<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddViolationsConnect extends Migration
{
    public function up()
    {
        Schema::create('connection_limit_violations', function (Blueprint $table) {
            $table->id();
            $table->uuid('key_activate_id')->index();
            $table->uuid('server_user_id')->index();
            $table->unsignedBigInteger('panel_id')->index();
            $table->bigInteger('user_tg_id')->nullable()->index();
            $table->unsignedSmallInteger('allowed_connections');
            $table->unsignedSmallInteger('actual_connections');
            $table->json('ip_addresses')->nullable();
            $table->unsignedInteger('violation_count')->default(1);
            $table->enum('status', ['active', 'resolved', 'ignored'])->default('active');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Внешние ключи
            $table->foreign('key_activate_id')
                ->references('id')
                ->on('key_activate')
                ->onDelete('cascade');

            $table->foreign('server_user_id')
                ->references('id')
                ->on('server_user')
                ->onDelete('cascade');

            $table->foreign('panel_id')
                ->references('id')
                ->on('panel')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('connection_limit_violations');
    }
}
