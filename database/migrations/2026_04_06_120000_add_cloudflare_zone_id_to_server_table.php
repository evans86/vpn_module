<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCloudflareZoneIdToServerTable extends Migration
{
    public function up(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->string('cloudflare_zone_id', 64)->nullable()->after('dns_record_id');
        });
    }

    public function down(): void
    {
        Schema::table('server', function (Blueprint $table) {
            $table->dropColumn('cloudflare_zone_id');
        });
    }
}
