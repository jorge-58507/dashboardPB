<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_sale>
 */
class DpbSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
	$date = $this->faker->dateTimeBetween('2025-11-01','2025-11-30');
	$dayOfWeek = $date->format('N');

        return [
            'sale_date' => $date->format('Y-m-d'),
	    'sale_userid' => 1,
		'sale_web' => ($date >= 5) ? $this->faker->numberBetween(1200,1450) : $this->faker->numberBetween(1400,1700),
		'sale_corporative' => ($date >= 5) ? $this->faker->numberBetween(0,100) : $this->faker->numberBetween(0,200),
		'sale_national' => ($date >= 5) ? $this->faker->numberBetween(2800,3400) : $this->faker->numberBetween(3400,4000),
		'sale_international' => ($date >= 5) ? $this->faker->numberBetween(18000,20000) : $this->faker->numberBetween(20000,22000),
		'sale_callcenter' => ($date >= 5) ? $this->faker->numberBetween(8000,9800) : $this->faker->numberBetween(10000,10700),
		'sale_ota' => ($date >= 5) ? $this->faker->numberBetween(800,1200) : $this->faker->numberBetween(1100,1500),
		'sale_arenas' => ($date >= 5) ? $this->faker->numberBetween(4500,5500) : $this->faker->numberBetween(5500,7500),
		'sale_status' => 1
        ];
    }
}
