<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiemExportProfileToDataConfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('data_configs', function (Blueprint $table) {
            $table->json('siem_export_profile')->nullable()->after('restriction_policy');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('data_configs', function (Blueprint $table) {
            $table->dropColumn('siem_export_profile');
        });
    }
}
