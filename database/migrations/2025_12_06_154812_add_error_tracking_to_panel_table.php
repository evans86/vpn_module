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
            $table->boolean('has_error')->default(false)->after('panel_status');
            $table->text('error_message')->nullable()->after('has_error');
            $table->timestamp('error_at')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn(['has_error', 'error_message', 'error_at']);
        });
    }
};
