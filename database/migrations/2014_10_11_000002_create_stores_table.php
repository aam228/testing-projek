<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wr_id');
            $table->foreign('wr_id')->references('id')->on('warehouses');
            $table->string('st_name');
            $table->string('st_email')->nullable();
            $table->string('st_phone')->nullable();
            $table->string('st_address')->nullable();
            $table->string('st_description')->nullable();
            $table->enum('st_delete', ['0', '1']);
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
        Schema::dropIfExists('stores');
    }
}
