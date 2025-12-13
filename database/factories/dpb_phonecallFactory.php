<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\dpb_phonecall;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon; // Necesario para manejar fechas secuenciales

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_phonecall>
 */
class DpbPhonecallFactory extends Factory
{
    protected $model = dpb_phonecall::class;

    /**
     * Define el estado por defecto del modelo (valores fijos o aleatorios simples).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Los campos de fecha y consumos condicionales serán sobrescritos por sequentialDates()
            'phonecall_date' => null, // Placeholder
            'phonecall_quantity' => 0, // Placeholder
            'phonecall_success' => 0,  // Placeholder
            'phonecall_average' => 0,  // Placeholder
            
            // Campos de valores fijos
            'phonecall_userid' => 1,
            'phonecall_status' => 1,
        ];
    }
    
    /**
     * Define la secuencia para generar 91 fechas únicas (01/09/2025 - 30/11/2025) 
     * y aplica la lógica de llamadas basada en el día de la semana.
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

            // Cantidad de llamadas: Más bajo el fin de semana.
            $quantity = $isWeekend ? $this->faker->numberBetween(180, 230) : $this->faker->numberBetween(230, 280);

            // Ventas Logradas: Proporcional a la cantidad, más bajo el fin de semana.
            $success = $isWeekend ? $this->faker->numberBetween(120, 160) : $this->faker->numberBetween(170, 250);
            
            // Tiempo Promedio: Típicamente más largo en días laborales si se atienden más casos complejos.
            $averageTime = $isWeekend ? $this->faker->numberBetween(7, 9) : $this->faker->numberBetween(8, 12);

            return [
                'phonecall_date' => $date->format('Y-m-d'),
                'phonecall_quantity' => $quantity,
                'phonecall_success' => $success,
                'phonecall_average' => $averageTime,
            ];
        });
    }
}