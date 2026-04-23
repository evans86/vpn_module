<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->boolean('warp_routing_enabled')->default(false)->after('selection_scope_meta');
            $table->string('warp_socks_host', 64)->default('127.0.0.1')->after('warp_routing_enabled');
            $table->unsignedSmallInteger('warp_socks_port')->nullable()->after('warp_socks_host');
        });
    }

    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn(['warp_routing_enabled', 'warp_socks_host', 'warp_socks_port']);
        });
    }
};
