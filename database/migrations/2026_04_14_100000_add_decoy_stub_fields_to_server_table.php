<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->boolean('decoy_stub_include_123_rar')->default(false)->after('logs_upload_enabled');
            $table->timestamp('decoy_stub_last_applied_at')->nullable()->after('decoy_stub_include_123_rar');
            $table->text('decoy_stub_last_message')->nullable()->after('decoy_stub_last_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->dropColumn([
                'decoy_stub_include_123_rar',
                'decoy_stub_last_applied_at',
                'decoy_stub_last_message',
            ]);
        });
    }
};
