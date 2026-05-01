<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            if (! Schema::hasColumn('panel', 'warp_bootstrap_dns_ips')) {
                $table->text('warp_bootstrap_dns_ips')->nullable()
                    ->comment('Резолверы DIRECT перед WARP — IPv4 через запятую; null = из .env (PANEL_WARP_FULL_DNS_DIRECT_IPS)');
            }
            if (! Schema::hasColumn('panel', 'warp_bootstrap_udp53_direct')) {
                $table->boolean('warp_bootstrap_udp53_direct')->default(true)
                    ->comment('Правило UDP/53→DIRECT до catch-all WARP');
            }
        });
    }

    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            if (Schema::hasColumn('panel', 'warp_bootstrap_udp53_direct')) {
                $table->dropColumn('warp_bootstrap_udp53_direct');
            }
            if (Schema::hasColumn('panel', 'warp_bootstrap_dns_ips')) {
                $table->dropColumn('warp_bootstrap_dns_ips');
            }
        });
    }
};
