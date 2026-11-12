<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ct_id');
            $table->foreign('ct_id')->references('id')->on('customer_types');
            $table->string('cust_name');
            $table->string('cust_phone');
            $table->string('cust_email')->nullable();
            $table->string('cust_province')->nullable();
            $table->string('cust_city')->nullable();
            $table->string('cust_subdistrict')->nullable();
            $table->string('cust_address')->nullable();
            $table->enum('cust_delete', ['0', '1']);
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
        Schema::dropIfExists('customers');
    }
}
