<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('a_id');
            $table->foreign('a_id')->references('id')->on('accounts');
            $table->string('stkt_name');
            $table->string('stkt_description')->nullable();
            $table->enum('stkt_delete', ['0', '1']);
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
        Schema::dropIfExists('stock_types');
    }
}
