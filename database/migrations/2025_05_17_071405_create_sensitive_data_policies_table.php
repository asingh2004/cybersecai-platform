<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSensitiveDataPoliciesTable extends Migration
{
    public function up()
    {
        Schema::create('sensitive_data_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('data_config_id')->nullable();
            $table->string('policy_name');
            $table->text('policy_url')->nullable();
            $table->string('policy_file')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sensitive_data_policies');
    }
}