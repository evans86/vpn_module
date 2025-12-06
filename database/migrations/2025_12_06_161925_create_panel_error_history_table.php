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
        Schema::create('panel_error_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_id');
            $table->text('error_message');
            $table->timestamp('error_occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->enum('resolution_type', ['manual', 'automatic'])->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->foreign('panel_id')->references('id')->on('panel')->onDelete('cascade');
            $table->index('panel_id');
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_error_history');
    }
};
