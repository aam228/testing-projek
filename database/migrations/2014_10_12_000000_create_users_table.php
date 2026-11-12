<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('st_id')->nullable();
            $table->unsignedBigInteger('stt_id')->nullable();
            $table->foreign('st_id')->references('id')->on('stores');
            $table->foreign('stt_id')->references('id')->on('store_types');
            $table->string('u_nip')->nullable();
            $table->string('u_ktp')->nullable();
            $table->string('u_secret_code')->nullable();
            $table->string('u_name');
            $table->string('u_email')->unique();
            $table->string('password');
            $table->string('u_phone');
            $table->string('u_address')->nullable();
            $table->enum('u_delete', ['0', '1']);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
