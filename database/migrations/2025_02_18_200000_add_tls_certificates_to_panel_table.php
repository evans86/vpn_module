<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->string('tls_certificate_path')->nullable()->after('config_updated_at');
            $table->string('tls_key_path')->nullable()->after('tls_certificate_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn(['tls_certificate_path', 'tls_key_path']);
        });
    }
};

