<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('account_name');
            $table->string('iban');
            $table->string('swift_code')->nullable();
            $table->string('routing_no')->nullable();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('branch_city')->nullable();
            $table->string('branch_address')->nullable();
            $table->string('description')->nullable();
            $table->string('country')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status',['Active', 'Inactive'])->default('Active');
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
        Schema::dropIfExists('banks');
    }
}
