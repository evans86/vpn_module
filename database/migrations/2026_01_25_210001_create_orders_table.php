<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pack_id')->unsigned()->nullable();
            $table->bigInteger('salesman_id')->unsigned()->nullable();
            $table->bigInteger('payment_method_id')->unsigned()->nullable();
            $table->integer('status')->default(0); // 0 - ожидает оплаты, 1 - ожидает подтверждения, 2 - одобрен, 3 - отклонен, 4 - отменен
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payment_proof')->nullable(); // Путь к файлу подтверждения оплаты
            $table->text('admin_comment')->nullable(); // Комментарий админа при отклонении
            $table->bigInteger('pack_salesman_id')->unsigned()->nullable(); // Связь с выданным пакетом
            $table->timestamps();

            $table->index('pack_id', 'orders_pack_idx');
            $table->index('salesman_id', 'orders_salesman_idx');
            $table->index('payment_method_id', 'orders_payment_method_idx');
            $table->index('status', 'orders_status_idx');
            $table->index('pack_salesman_id', 'orders_pack_salesman_idx');

            $table->foreign('pack_id', 'orders_pack_fk')
                  ->references('id')
                  ->on('pack')
                  ->onDelete('cascade');

            $table->foreign('salesman_id', 'orders_salesman_fk')
                  ->references('id')
                  ->on('salesman')
                  ->onDelete('cascade');

            $table->foreign('payment_method_id', 'orders_payment_method_fk')
                  ->references('id')
                  ->on('payment_methods')
                  ->onDelete('set null');

            $table->foreign('pack_salesman_id', 'orders_pack_salesman_fk')
                  ->references('id')
                  ->on('pack_salesman')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}

