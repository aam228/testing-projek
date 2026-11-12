<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderArticleDetailStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_article_detail_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poad_id');
            $table->foreign('poad_id')->references('id')->on('purchase_order_article_details');
            $table->integer('poads_qty');
            $table->double('poads_discount')->nullable();
            $table->double('poads_extra_discount')->nullable();
            $table->double('poads_sub_discount')->nullable();
            $table->double('poads_purchase_price')->nullable();
            $table->double('poads_total_price')->nullable();
            $table->enum('poads_type', ['IN', 'OUT']);
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
        Schema::dropIfExists('purchase_order_article_detail_statuses');
    }
}
