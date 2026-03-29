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
        Schema::table('server', function (Blueprint $table) {
            if (!Schema::hasColumn('server', 'ssh_port')) {
                $table->unsignedSmallInteger('ssh_port')->nullable()->after('host');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server', function (Blueprint $table) {
            if (Schema::hasColumn('server', 'ssh_port')) {
                $table->dropColumn('ssh_port');
            }
        });
    }
};
