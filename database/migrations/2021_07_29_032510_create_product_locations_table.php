<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('st_id');
            $table->foreign('st_id')->references('id')->on('stores');
            $table->string('pl_code');
            $table->string('pl_name');
            $table->string('pl_description')->nullable();
            $table->enum('pl_delete', ['0', '1']);
            $table->enum('pl_default', ['0', '1']);
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
        Schema::dropIfExists('product_locations');
    }
}
