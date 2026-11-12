<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductStockTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ps_id');
            $table->foreign('ps_id')->references('id')->on('product_stocks');
            $table->integer('pst_qty');
            $table->double('pst_price');
            $table->string('pst_description')->nullable();
            $table->enum('pst_type', ['IN', 'OUT']);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
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
        Schema::dropIfExists('product_stock_transactions');
    }
}
