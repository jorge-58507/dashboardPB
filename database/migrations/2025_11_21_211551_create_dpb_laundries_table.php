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
        Schema::create('dpb_laundries', function (Blueprint $table) {
            $table->bigIncrements('laundry_id');
            $table->unsignedBigInteger('laundry_userid');
            $table->datetime('laundry_dateInit');
            $table->datetime('laundry_dateFinish');
            $table->float('laundry_total');
            $table->integer('laundry_cycle');
            $table->integer('laundry_status');
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
        Schema::dropIfExists('dpb_laundries');
    }
};
