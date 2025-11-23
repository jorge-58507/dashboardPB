<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dpb_sales', function (Blueprint $table) {
            $table->float('income_ab')->nullable()->after('sale_web');
            $table->float('income_other')->nullable()->after('income_ab');
            $table->integer('income_userid')->nullable()->after('income_other');
            $table->integer('income_status')->nullable()->after('income_other');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dpb_sales', function (Blueprint $table) {
            $table->dropColumn(['income_ab', 'income_other','income_userid']);
        });
    }
};
