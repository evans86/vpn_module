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
            $table->text('reality_private_key')->nullable()->after('error_at');
            $table->string('reality_public_key')->nullable()->after('reality_private_key');
            $table->string('reality_short_id')->nullable()->after('reality_public_key');
            $table->string('reality_grpc_short_id')->nullable()->after('reality_short_id');
            $table->timestamp('reality_keys_generated_at')->nullable()->after('reality_grpc_short_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn([
                'reality_private_key',
                'reality_public_key',
                'reality_short_id',
                'reality_grpc_short_id',
                'reality_keys_generated_at'
            ]);
        });
    }
};

