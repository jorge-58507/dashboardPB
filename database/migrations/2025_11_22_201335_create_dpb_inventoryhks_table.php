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
        Schema::create('dpb_inventoryhks', function (Blueprint $table) {
            $table->bigIncrements('inventoryhk_id');
            $table->unsignedBigInteger('inventoryhk_userid');
            $table->datetime('inventoryhk_date');
            $table->integer('inventoryhk_kSheet');
            $table->integer('inventoryhk_qSheet');
            $table->integer('inventoryhk_kPillowcase');
            $table->integer('inventoryhk_qPillowcase');
            $table->integer('inventoryhk_kPillow');
            $table->integer('inventoryhk_qPillow');
            $table->integer('inventoryhk_kMattressprotector');
            $table->integer('inventoryhk_qMattressprotector');
            $table->integer('inventoryhk_towel');
            $table->integer('inventoryhk_handTowel');
            $table->integer('inventoryhk_feetTowel');
            $table->integer('inventoryhk_faceTowel');
            $table->integer('inventoryhk_poolTowel');
            $table->integer('inventoryhk_blueBlanket');
            $table->integer('inventoryhk_greenBlanket');
            $table->integer('inventoryhk_kDuvet');
            $table->integer('inventoryhk_qDuvet');
            $table->integer('inventoryhk_kCover');
            $table->integer('inventoryhk_qCover');
            $table->integer('inventoryhk_kBedskirt');
            $table->integer('inventoryhk_qBedskirt');
            $table->integer('inventoryhk_status');
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
        Schema::dropIfExists('dpb_inventoryhks');
    }
};
