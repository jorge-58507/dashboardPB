<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\dpb_sale;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon; // Necesario para manejar fechas secuenciales

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_sale>
 */
class dpb_saleFactory extends Factory
{
    protected $model = dpb_sale::class;

    /**
     * Define el estado por defecto del modelo (valores fijos o aleatorios simples).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Los campos de fecha y consumos condicionales serán sobrescritos por sequentialDates()
            'sale_date' => null, // Placeholder

            // Placeholder para los campos que serán condicionales
            'sale_web' => 0, 
            'sale_corporative' => 0, 
            'sale_national' => 0, 
            'sale_international' => 0, 
            'sale_callcenter' => 0, 
            'sale_ota' => 0, 
            'sale_arenas' => 0, 
            
            // Campos de valores fijos
            'sale_userid' => 1,
            'sale_status' => 1,
        ];
    }
    
    /**
     * Define la secuencia para generar 91 fechas únicas (01/09/2025 - 30/11/2025) 
     * y aplica la lógica de ventas basada en el día de la semana.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sequentialDates(): Factory
    {
        $startDate = Carbon::parse('2025-09-01');
        
        return $this->sequence(function (Sequence $sequence) use ($startDate) {
            
            // Genera la fecha secuencial (añade el índice de 0 a 90)
            $date = $startDate->clone()->addDays($sequence->index);
            $dayOfWeek = $date->format('N'); // 1=Lunes, 7=Domingo

            // Lógica Condicional: Día de Semana (Lunes-Jueves: 1-4) vs. Fin de Semana (Viernes-Domingo: 5-7)
            $isWeekend = ($dayOfWeek >= 5); // true si es Viernes, Sábado o Domingo

            // Definición de rangos basada en tu lógica original (ajustando la comparación a $isWeekend)
            return [
                'sale_date' => $date->format('Y-m-d'),
                
                // Las ventas en la WEB tienden a ser más bajas el fin de semana según tu lógica
                'sale_web' => $isWeekend ? $this->faker->numberBetween(1200,1450) : $this->faker->numberBetween(1400,1700),
                
                // Corporativas tienden a ser más bajas el fin de semana
                'sale_corporative' => $isWeekend ? $this->faker->numberBetween(0,100) : $this->faker->numberBetween(0,200),
                
                // Nacional tiende a ser más bajo el fin de semana
                'sale_national' => $isWeekend ? $this->faker->numberBetween(2800,3400) : $this->faker->numberBetween(3400,4000),
                
                // Internacional tiende a ser más bajo el fin de semana
                'sale_international' => $isWeekend ? $this->faker->numberBetween(18000,20000) : $this->faker->numberBetween(20000,22000),
                
                // Call Center tiende a ser más bajo el fin de semana
                'sale_callcenter' => $isWeekend ? $this->faker->numberBetween(8000,9800) : $this->faker->numberBetween(10000,10700),
                
                // OTA (Online Travel Agency) tienden a ser más bajos el fin de semana
                'sale_ota' => $isWeekend ? $this->faker->numberBetween(800,1200) : $this->faker->numberBetween(1100,1500),
                
                // Arenas tiende a ser más bajo el fin de semana
                'sale_arenas' => $isWeekend ? $this->faker->numberBetween(4500,5500) : $this->faker->numberBetween(5500,7500),
            ];
        });
    }
}