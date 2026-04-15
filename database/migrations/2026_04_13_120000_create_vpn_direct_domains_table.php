<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVpnDirectDomainsTable extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_direct_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 253)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_direct_domains');
    }
}
