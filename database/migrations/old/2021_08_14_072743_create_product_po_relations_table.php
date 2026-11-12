<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPoRelationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_po_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('p_id');
            $table->unsignedBigInteger('poas_id');
            $table->foreign('p_id')->references('id')->on('products');
            $table->foreign('poas_id')->references('id')->on('purchase_order_article_statuses');
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
        Schema::dropIfExists('product_po_relations');
    }
}
