<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductStockDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_stock_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pst_id');
            $table->foreign('pst_id')->references('id')->on('products');
            $table->double('psd_discount');
            $table->double('psd_min_order');
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
        Schema::dropIfExists('product_stock_discounts');
    }
}
