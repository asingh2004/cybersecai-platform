<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiemRefsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('siem_refs', function (Blueprint $table) {
          	$table->id();
            $table->string('name'); // Splunk, QRadar, Elastic, Sentinel, ArcSight, LogRhythm, etc.
            $table->string('format'); // JSON, CEF, LEEF, etc.
            $table->json('template_field_map')->nullable(); // Default mapping suggestion
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
        Schema::dropIfExists('siem_refs');
    }
}
