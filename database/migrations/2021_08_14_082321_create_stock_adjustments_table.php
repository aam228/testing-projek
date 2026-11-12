<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockAdjustmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pst_id');
            $table->unsignedBigInteger('pl_id');
            $table->foreign('pst_id')->references('id')->on('product_stocks');
            $table->foreign('pl_id')->references('id')->on('product_locations');
            $table->integer('sa_qty_before');
            $table->integer('sa_qty_after');
            $table->enum('sa_type', ['IN', 'OUT']);
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
        Schema::dropIfExists('stock_adjustments');
    }
}
