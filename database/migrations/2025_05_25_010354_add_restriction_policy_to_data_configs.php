<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRestrictionPolicyToDataConfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('data_configs', function (Blueprint $table) {
            $table->json('restriction_policy')->nullable()->after('data_sources');
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
            $table->dropColumn('restriction_policy');
        });
    }
}
