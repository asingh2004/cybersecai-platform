<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgenticAiLogsTable extends Migration
{
    public function up()
    {
        Schema::create('agentic_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Now required, NOT nullable
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('endpoint', 64);
            $table->json('request_data');
            $table->json('response_data')->nullable();
            $table->string('status', 20)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('response_ms')->nullable();
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('agentic_ai_logs');
    }
}