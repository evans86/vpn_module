<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->string('key_activate_id')->comment('Ключ для определения канала отправки (модуль/пакет)');
            $table->string('status', 20)->default('pending')->comment('pending|delivered|failed');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->index(['broadcast_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
