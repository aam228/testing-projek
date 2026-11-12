<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('br_id');
            $table->unsignedBigInteger('pc_id');
            $table->unsignedBigInteger('psc_id');
            $table->unsignedBigInteger('pssc_id');
            $table->unsignedBigInteger('mc_id');
            $table->unsignedBigInteger('ps_id');
            $table->unsignedBigInteger('pu_id');
            $table->unsignedBigInteger('gn_id');
            $table->unsignedBigInteger('ss_id');
            $table->foreign('br_id')->references('id')->on('brands');
            $table->foreign('pc_id')->references('id')->on('product_categories');
            $table->foreign('psc_id')->references('id')->on('product_sub_categories');
            $table->foreign('pssc_id')->references('id')->on('product_sub_sub_categories');
            $table->foreign('mc_id')->references('id')->on('main_colors');
            $table->foreign('ps_id')->references('id')->on('product_suppliers');
            $table->foreign('pu_id')->references('id')->on('product_units');
            $table->foreign('gn_id')->references('id')->on('genders')->nullable();
            $table->foreign('ss_id')->references('id')->on('seasons')->nullable();
            $table->string('p_code')->nullable();
            $table->string('p_color');
            $table->string('p_name');
            $table->string('p_aging')->nullable();
            $table->double('p_price_tag')->nullable();
            $table->double('p_sell_price')->nullable();
            $table->double('p_purchase_price')->nullable();
            $table->text('p_description')->nullable();
            $table->text('p_image')->nullable();
            $table->enum('p_delete', ['0', '1']);
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
        Schema::dropIfExists('products');
    }
}
