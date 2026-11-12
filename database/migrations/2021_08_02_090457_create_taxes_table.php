<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('a_id_purchase');
            $table->unsignedBigInteger('a_id_sell');
            $table->foreign('a_id_purchase')->references('id')->on('accounts');
            $table->foreign('a_id_sell')->references('id')->on('accounts');
            $table->string('tx_code');
            $table->string('tx_name');
            $table->double('tx_npwp');
            $table->double('tx_non_npwp')->nullable();
            $table->enum('tx_delete', ['0', '1']);
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
        Schema::dropIfExists('taxes');
    }
}
