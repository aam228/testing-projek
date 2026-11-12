<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountClassificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_classifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('at_id');
            $table->foreign('at_id')->references('id')->on('account_types');
            $table->string('ac_name');
            $table->string('ac_description')->nullable();
            $table->enum('ac_delete', ['0', '1']);
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
        Schema::dropIfExists('account_classifications');
    }
}
