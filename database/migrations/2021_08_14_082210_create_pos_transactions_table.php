<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('u_id');
            $table->unsignedBigInteger('st_id');
            $table->unsignedBigInteger('vc_id')->nullable();
            $table->unsignedBigInteger('stt_id');
            $table->unsignedBigInteger('std_id');
            $table->unsignedBigInteger('cust_id');
            $table->unsignedBigInteger('sub_cust_id')->nullable();
            $table->unsignedBigInteger('pm_id');
            $table->unsignedBigInteger('cr_id')->nullable();
            $table->foreign('u_id')->references('id')->on('users');
            $table->foreign('st_id')->references('id')->on('stores');
            $table->foreign('vc_id')->references('id')->on('vouchers');
            $table->foreign('stt_id')->references('id')->on('store_types');
            $table->foreign('std_id')->references('id')->on('store_type_divisions');
            $table->foreign('cust_id')->references('id')->on('customers');
            $table->foreign('sub_cust_id')->references('id')->on('customers');
            $table->foreign('pm_id')->references('id')->on('payment_methods');
            $table->foreign('cr_id')->references('id')->on('couriers');
            $table->string('pos_invoice');
            $table->integer('pos_unique_code')->nullable();
            $table->string('pos_card_number')->nullable();
            $table->string('pos_ref_number')->nullable();
            $table->string('pos_order_number')->nullable();
            $table->double('pos_admin_cost')->nullable();
            $table->double('pos_real_price')->nullable();
            $table->double('pos_discount')->nullable();
            $table->double('pos_shipping')->nullable();
            $table->string('pos_shipping_number')->nullable();
            $table->string('pos_note')->nullable();
            $table->text('pos_image')->nullable();
            $table->enum('pos_draft', ['0', '1']);
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
        Schema::dropIfExists('pos_transactions');
    }
}
