<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomActivationSuccessTextToSalesman extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (! Schema::hasColumn('salesman', 'custom_activation_success_text')) {
                $table->mediumText('custom_activation_success_text')->nullable()->after('custom_help_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (Schema::hasColumn('salesman', 'custom_activation_success_text')) {
                $table->dropColumn('custom_activation_success_text');
            }
        });
    }
}
