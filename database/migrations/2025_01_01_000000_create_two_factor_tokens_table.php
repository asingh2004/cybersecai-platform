<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('two_factor_tokens', function (Blueprint $table) {
            $table->bigIncrements('id'); // primary key for this table
            $table->unsignedInteger('user_id')->index(); // must match users.id type (INT UNSIGNED)
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            // IMPORTANT: No foreign key constraint to avoid the 3780 error for now
            // If you want to try later, add:
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_tokens');
    }
};