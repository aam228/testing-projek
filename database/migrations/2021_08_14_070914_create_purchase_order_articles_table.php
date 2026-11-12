<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->unsignedBigInteger('p_id');
            $table->foreign('po_id')->references('id')->on('purchase_orders');
            $table->foreign('p_id')->references('id')->on('products');
            $table->double('poa_discount')->nullable();
            $table->double('poa_extra_discount')->nullable();
            $table->double('poa_sub_discount')->nullable();
            $table->string('poa_reminder')->nullable();
            $table->enum('poa_draft', ['0', '1']);
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
        Schema::dropIfExists('purchase_order_articles');
    }
}
