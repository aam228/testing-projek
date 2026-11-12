<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductLocationSetupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_location_setups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pst_id');
            $table->unsignedBigInteger('pl_id');
            $table->foreign('pst_id')->references('id')->on('product_stocks');
            $table->foreign('pl_id')->references('id')->on('product_locations');
            $table->integer('pls_qty');
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
        Schema::dropIfExists('product_location_setups');
    }
}
