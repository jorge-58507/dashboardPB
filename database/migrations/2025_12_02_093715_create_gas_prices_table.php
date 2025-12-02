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
        Schema::create('dpb_gasprices', function (Blueprint $table) {
            $table->bigIncrements('gasprice_id');
            $table->unsignedBigInteger('gasprice_userid');
            $table->float('gasprice_price');
            $table->integer('gasprice_status');
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
        Schema::dropIfExists('gas_prices');
    }
};
