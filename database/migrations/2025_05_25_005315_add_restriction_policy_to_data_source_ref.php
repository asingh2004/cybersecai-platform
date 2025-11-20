<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRestrictionPolicyToDataSourceRef extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('data_source_ref', function (Blueprint $table) {
            $table->json('restriction_policy')->nullable()->after('storage_type_config');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('data_source_ref', function (Blueprint $table) {
            $table->dropColumn('restriction_policy');
        });
    }
}
