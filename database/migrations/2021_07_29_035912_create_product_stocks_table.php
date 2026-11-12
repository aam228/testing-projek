<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('p_id');
            $table->unsignedBigInteger('sz_id');
            $table->foreign('p_id')->references('id')->on('products');
            $table->foreign('sz_id')->references('id')->on('sizes');
            $table->integer('ps_qty');
            $table->string('ps_barcode')->nullable();
            $table->string('ps_running_code')->nullable();
            $table->double('ps_price_tag')->nullable();
            $table->double('ps_sell_price')->nullable();
            $table->double('ps_purchase_price')->nullable();
            $table->enum('ps_delete', ['0', '1']);
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
        Schema::dropIfExists('product_stocks');
    }
}
