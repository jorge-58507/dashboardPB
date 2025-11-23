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
        Schema::create('dpb_sales', function (Blueprint $table) {
            $table->bigIncrements('sale_id');
            $table->datetime('sale_date');
            $table->unsignedBigInteger('sale_userid');
            $table->float('sale_corporative')->nullable();
            $table->float('sale_national')->nullable();
            $table->float('sale_international')->nullable();
            $table->float('sale_callcenter')->nullable();
            $table->float('sale_ota')->nullable();
            $table->float('sale_arenas')->nullable();
            $table->float('sale_web')->nullable();
            $table->integer('sale_status')->nullable();
            $table->timestamps();


            $table->foreign('sale_userid')
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
        Schema::dropIfExists('m_sales');
    }
};
