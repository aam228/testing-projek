<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderItemDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_item_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poi_id');
            $table->foreign('poi_id')->references('id')->on('purchase_order_items');
            $table->integer('poid_qty');
            $table->string('poid_description')->nullable();
            $table->enum('poid_delete', ['0', '1']);
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
        Schema::dropIfExists('purchase_order_item_details');
    }
}
