<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('c_id');
            $table->unsignedBigInteger('br_id');
            $table->foreign('c_id')->references('id')->on('customers');
            $table->foreign('br_id')->references('id')->on('brands');
            $table->double('cd_discount');
            $table->double('cd_min_order');
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
        Schema::dropIfExists('customer_discounts');
    }
}
