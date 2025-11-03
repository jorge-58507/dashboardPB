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
        Schema::create('dpb_gasconsumptions', function (Blueprint $table) {
            $table->bigIncrements('gasconsumption_id');
            $table->datetime('gasconsumption_date');
            $table->unsignedBigInteger('gasconsumption_userid');
            $table->float('gasconsumption_cala');
            $table->float('gasconsumption_laundry');
            $table->float('gasconsumption_kitchen');
            $table->float('gasconsumption_velero');
            $table->float('gasconsumption_hotwater');
            $table->float('gasconsumption_price');
            $table->integer('gasconsumption_status')->default('1');
            $table->timestamps();    
            
            
            $table->foreign('gasconsumption_userid')
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
        Schema::dropIfExists('dpb_gasconsumptions');
    }
};
