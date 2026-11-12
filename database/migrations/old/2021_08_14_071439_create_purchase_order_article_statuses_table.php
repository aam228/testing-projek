<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderArticleStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_article_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->unsignedBigInteger('poa_id');
            $table->unsignedBigInteger('poad_id');
            $table->foreign('po_id')->references('id')->on('purchase_orders');
            $table->foreign('poa_id')->references('id')->on('purchase_order_articles');
            $table->foreign('poad_id')->references('id')->on('purchase_order_article_details');
            $table->integer('poas_qty');
            $table->double('poas_discount')->nullable();
            $table->double('poas_extra_discount')->nullable();
            $table->double('poas_purchase_price')->nullable();
            $table->double('poas_total_price')->nullable();
            $table->enum('poas_status', ['cbd', 'consigment']);
            $table->enum('poas_type', ['IN', 'OUT']);
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
        Schema::dropIfExists('purchase_order_article_statuses');
    }
}
