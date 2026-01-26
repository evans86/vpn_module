<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название способа оплаты (например, "Сбербанк", "QIWI", "Криптовалюта")
            $table->string('type')->default('bank'); // Тип: bank, crypto, ewallet, other
            $table->text('details'); // Детали для перевода (номер карты, кошелька и т.д.)
            $table->text('instructions')->nullable(); // Инструкции для пользователя
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // Порядок отображения
            $table->timestamps();

            $table->index('is_active', 'payment_methods_active_idx');
            $table->index('sort_order', 'payment_methods_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
}

