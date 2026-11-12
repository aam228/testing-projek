<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('a_id');
            $table->unsignedBigInteger('stt_id');
            $table->foreign('a_id')->references('id')->on('accounts');
            $table->foreign('stt_id')->references('id')->on('store_types');
            $table->string('pm_name');
            $table->string('pm_description')->nullable();
            $table->enum('pm_delete', ['0', '1']);
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
        Schema::dropIfExists('payment_methods');
    }
}
