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
        Schema::create('dpb_phonecalls', function (Blueprint $table) {
            $table->bigIncrements('phonecall_id');
            $table->datetime('phonecall_date');
            $table->unsignedBigInteger('phonecall_userid');
            $table->integer('phonecall_quantity');
            $table->integer('phonecall_success');
            $table->float('phonecall_average');
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
        Schema::dropIfExists('dpb_phonecalls');
    }
};
