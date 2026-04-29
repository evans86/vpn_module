<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Секрет GET /test-speed для сводной проверки из панели (хранится зашифрованным cast в модели).
     */
    public function up(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->text('decoy_stub_test_speed_token')->nullable()->after('decoy_stub_last_message');
        });
    }

    public function down(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->dropColumn('decoy_stub_test_speed_token');
        });
    }
};
