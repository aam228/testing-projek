<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSubSubCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_sub_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psc_id');
            $table->foreign('psc_id')->references('id')->on('product_sub_categories');
            $table->string('pssc_name');
            $table->string('pssc_description')->nullable();
            $table->enum('pssc_delete', ['0', '1']);
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
        Schema::dropIfExists('product_sub_sub_categories');
    }
}
