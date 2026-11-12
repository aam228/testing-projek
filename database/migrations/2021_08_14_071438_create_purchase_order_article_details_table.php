<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderArticleDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_article_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poa_id');
            $table->unsignedBigInteger('pst_id');
            $table->foreign('poa_id')->references('id')->on('purchase_order_articles');
            $table->foreign('pst_id')->references('id')->on('product_stocks');
            $table->integer('poad_qty')->nullable();
            $table->double('poad_purchase_price')->nullable();
            $table->double('poad_total_price')->nullable();
            $table->enum('poad_draft', ['0', '1']);
            $table->timestamps();
        });
    }

    // Schema::create('purchase_order_article_details', function (Blueprint $table) {
    //     $table->id();
    //     $table->unsignedBigInteger('poa_id');
    //     $table->unsignedBigInteger('pst_id');
    //     $table->foreign('poa_id')->references('id')->on('purchase_order_articles');
    //     $table->foreign('pst_id')->references('id')->on('product_stocks');
    //     $table->integer('poad_qty')->nullable();
    //     $table->integer('poad_qty_confirm')->nullable();
    //     $table->integer('poad_qty_return')->nullable();
    //     $table->double('poad_purchase_price')->nullable();
    //     $table->double('poad_total_price')->nullable();
    //     $table->double('poad_total_confirm_price')->nullable();
    //     $table->double('poad_total_return_price')->nullable();
    //     $table->enum('poad_type', ['IN', 'OUT']);
    //     $table->enum('poad_draft', ['0', '1']);
    //     $table->timestamps();
    // });
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_order_article_details');
    }
}
