<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductLocationSetupTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_location_setup_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pls_id');
            $table->unsignedBigInteger('u_id');
            $table->unsignedBigInteger('u_id_packer')->nullable();
            $table->unsignedBigInteger('pt_id')->nullable();
            $table->foreign('pls_id')->references('id')->on('product_location_setups');
            $table->foreign('u_id')->references('id')->on('users');
            $table->foreign('u_id_packer')->references('id')->on('users');
            $table->foreign('pt_id')->references('id')->on('pos_transactions');
            $table->integer('plst_qty');
            $table->enum('plst_type', ['IN', 'OUT']);
            $table->enum('plst_status', ['INSTOCK', 'WAITING OFFLINE', 'WAITING ONLINE', 'WAITING FOR PACKING', 'DONE', 'REJECT', 'REFUND']);
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
        Schema::dropIfExists('product_location_setup_transactions');
    }
}
