<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server', function (Blueprint $table) {
            if (! Schema::hasColumn('server', 'timeweb_api_profile')) {
                $table->string('timeweb_api_profile', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('server', function (Blueprint $table) {
            if (Schema::hasColumn('server', 'timeweb_api_profile')) {
                $table->dropColumn('timeweb_api_profile');
            }
        });
    }
};
