<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosTransactionDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_transaction_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pt_id')->nullable();
            $table->unsignedBigInteger('pst_id');
            $table->unsignedBigInteger('pl_id')->nullable();
            $table->foreign('pt_id')->references('id')->on('pos_transactions');
            $table->foreign('pst_id')->references('id')->on('product_stocks');
            $table->foreign('pl_id')->references('id')->on('product_locations');
            $table->integer('pos_td_qty');
            $table->integer('pos_td_qty_pickup')->nullable();
            $table->double('pos_td_discount')->nullable();
            $table->double('pos_td_discount_price')->nullable();
            $table->double('pos_td_sell_price');
            $table->double('pos_td_total_price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_transaction_details');
    }
}
