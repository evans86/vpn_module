<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лог успешно обработанных заданий очереди (для мониторинга на странице «Очередь заданий»).
 */
class CreateProcessedJobsTable extends Migration
{
    public function up(): void
    {
        Schema::create('processed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->nullable()->index();
            $table->string('queue', 64)->default('default');
            $table->string('job_name', 255)->nullable();
            $table->timestamp('processed_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_jobs');
    }
}
