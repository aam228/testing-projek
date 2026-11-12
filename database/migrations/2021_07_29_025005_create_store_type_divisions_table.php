<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoreTypeDivisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('store_type_divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stt_id');
            $table->foreign('stt_id')->references('id')->on('store_types');
            $table->string('dv_name');
            $table->string('dv_description')->nullable();
            $table->enum('dv_delete', ['0', '1']);
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
        Schema::dropIfExists('store_type_divisions');
    }
}
