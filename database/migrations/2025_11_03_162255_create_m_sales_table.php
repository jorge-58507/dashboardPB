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
            $table->float('sale_corporative');
            $table->float('sale_national');
            $table->float('sale_international');
            $table->float('sale_callcenter');
            $table->float('sale_ota');
            $table->float('sale_arenas');
            $table->float('sale_web');
            $table->integer('sale_status');
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
