<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFleetTerritoryReportsTable extends Migration
{
    /**
     * Внешние отчёты (проба из другой территории — PowerShell/скрипт): сырой текст + извлечённый GeoIP.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fleet_territory_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_note', 255)->nullable()->comment('Метка точки / кто прислал');

            $table->string('mode_label', 512)->nullable()->comment('Режим из отчёта (прямое / прокси)');
            $table->string('country_code', 16)->nullable();
            $table->string('country_name', 255)->nullable();
            $table->string('region', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('isp', 512)->nullable();
            $table->string('asn', 128)->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->string('geo_service', 128)->nullable();
            $table->text('geo_parse_error')->nullable()->comment('Ошибка GeoIP из отчёта или текст «пропущено»');

            $table->longText('raw_report');

            $table->timestamps();

            $table->index(['country_code']);
            $table->index(['created_at']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fleet_territory_reports');
    }
}
