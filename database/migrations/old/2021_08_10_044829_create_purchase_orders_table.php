<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('st_id')->nullable();
            $table->unsignedBigInteger('ps_id')->nullable();
            $table->foreign('st_id')->references('id')->on('stores');
            $table->foreign('ps_id')->references('id')->on('product_suppliers');
            $table->string('po_code');
            $table->text('po_description')->nullable();
            $table->enum('po_delete', ['0', '1']);
            $table->enum('po_draft', ['0', '1']);
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
        Schema::dropIfExists('purchase_orders');
    }
}
