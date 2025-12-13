<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\dpb_income; // Asegúrate de que esta ruta sea correcta
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon; // Necesario para manejar fechas secuenciales

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_income>
 */
class dpb_incomeFactory extends Factory
{
    protected $model = dpb_income::class;

    /**
     * Define el estado por defecto del modelo (valores fijos).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Los campos de fecha e ingresos serán sobrescritos por sequentialDates()
            'income_date' => null, // Placeholder
            'income_ab' => 0,      // Placeholder
            'income_another' => 0, // Placeholder
            
            // Campos de valores fijos
            'income_userid' => 1,
            'income_status' => 1,
        ];
    }
    
    /**
     * Define la secuencia para generar 91 fechas únicas (01/09/2025 - 30/11/2025) 
     * y aplica la lógica de ingresos basada en el día de la semana.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sequentialDates(): Factory
    {
        // Rango de fechas: Septiembre 1 a Noviembre 30
        $startDate = Carbon::parse('2025-09-01');
        
        return $this->sequence(function (Sequence $sequence) use ($startDate) {
            
            // Genera la fecha secuencial (añade el índice de 0 a 90)
            $date = $startDate->clone()->addDays($sequence->index);
            $dayOfWeek = $date->format('N'); // 1=Lunes, 7=Domingo

            // Lógica Condicional: Ingresos más altos en Fin de Semana (Viernes-Domingo: N>=5)
            $isWeekend = ($dayOfWeek >= 5); 

            // Ingresos A&B (Promedio objetivo: 295)
            $ab_income = $isWeekend 
                ? $this->faker->numberBetween(310, 350) // Alto en fin de semana
                : $this->faker->numberBetween(260, 300); // Promedio entre semana

            // Otros Ingresos (Promedio objetivo: 400)
            $other_income = $isWeekend 
                ? $this->faker->numberBetween(420, 480) // Alto en fin de semana
                : $this->faker->numberBetween(380, 420); // Promedio entre semana


            return [
                'income_date' => $date->format('Y-m-d'),
                'income_ab' => $ab_income,
                'income_another' => $other_income,
            ];
        });
    }
}