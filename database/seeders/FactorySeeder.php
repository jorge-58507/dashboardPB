<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\dpb_gasconsumption;
use App\Models\dpb_sale;
use App\Models\dpb_income;
use App\Models\dpb_laundry;
use App\Models\dpb_phonecall;


class FactorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        dpb_gasconsumption::factory()->sequentialDates()->count(91)->create();
        dpb_sale::factory()->sequentialDates()->count(91)->create();
        dpb_phonecall::factory()->sequentialDates()->count(91)->create();
        dpb_laundry::factory()->sequentialRanges()->count(46)->create();
        dpb_income::factory()->sequentialDates()->count(91)->create();
    }
}
