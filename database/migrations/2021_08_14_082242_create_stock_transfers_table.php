<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pst_id');
            $table->unsignedBigInteger('stf_id_from');
            $table->unsignedBigInteger('stf_id_to');
            $table->foreign('pst_id')->references('id')->on('product_stocks');
            $table->foreign('stf_id_from')->references('id')->on('stores');
            $table->foreign('stf_id_to')->references('id')->on('stores');
            $table->integer('stf_qty');
            $table->enum('stf_type', ['IN', 'OUT']);
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
        Schema::dropIfExists('stock_transfers');
    }
}
