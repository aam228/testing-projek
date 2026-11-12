<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sub_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cust_id');
            $table->foreign('cust_id')->references('id')->on('customers');
            $table->string('sub_cust_name');
            $table->string('sub_cust_phone');
            $table->string('sub_cust_email')->nullable();
            $table->string('sub_cust_province')->nullable();
            $table->string('sub_cust_city')->nullable();
            $table->string('sub_cust_subdistrict')->nullable();
            $table->string('sub_cust_address')->nullable();
            $table->enum('sub_cust_delete', ['0', '1']);
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
        Schema::dropIfExists('sub_customers');
    }
}
