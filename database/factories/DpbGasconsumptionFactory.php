<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\models\dpb_gasconsumption;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_gasconsumption>
 */
class DpbGasconsumptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = dpb_gasconsumption::class;

    public function definition()
    {
        $date = $this->faker->dateTimeBetween('2025-11-01', '2025-11-30');
	$dayOfWeek = $date->format('N');

        $cala = ($dayOfWeek >= 5) ? $this->faker->numberBetween(100,250) : 0;
        $velero = ($dayOfWeek >= 5) ? $this->faker->numberBetween(80,200) : 0;

        return [
            'gasconsumption_date' => $date->format('Y-m-d'),
	    'gasconsumption_cala' => $cala,
            'gasconsumption_velero' => $velero,
       	    'gasconsumption_hotwater' => $this->numberBetween(200,220),
	    'gasconsumption_laundry' => 190,
	    'gasconsumption_kitchen' => $this->numberBetween(330,370),
	    'gasconsumption_price' => 0.50,
	    'gasconsumption_status' => 1,
	    'gasconsumption_userid' => 2
        ];
    }
}
