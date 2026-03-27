<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActivationSuccessKeyboardLinksToSalesman extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (! Schema::hasColumn('salesman', 'activation_success_keyboard_links')) {
                $table->text('activation_success_keyboard_links')->nullable()->after('custom_activation_success_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (Schema::hasColumn('salesman', 'activation_success_keyboard_links')) {
                $table->dropColumn('activation_success_keyboard_links');
            }
        });
    }
}
