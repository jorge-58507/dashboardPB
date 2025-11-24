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
        Schema::create('dpb_incomes', function (Blueprint $table) {
            $table->bigIncrements('income_id');
            $table->datetime('income_date');
            $table->unsignedBigInteger('income_userid');
            $table->float('income_ab')->nullable();
            $table->float('income_other')->nullable();
            $table->integer('income_status')->nullable();
            $table->timestamps();


            $table->foreign('income_userid')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dpb_incomes');
    }
};
