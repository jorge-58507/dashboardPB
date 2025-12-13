<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\dpb_gasconsumption;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon;

class dpb_gasconsumptionFactory extends Factory
{
    /**
     * El modelo correspondiente al Factory.
     *
     * @var string
     */
    protected $model = dpb_gasconsumption::class;

    /**
     * Define el estado por defecto del modelo (valores fijos o aleatorios simples).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gasconsumption_date' => null, // Placeholder
            'gasconsumption_cala' => 0, // Placeholder
            'gasconsumption_velero' => 0, // Placeholder
            'gasconsumption_laundry' => 190, // Fijo
            'gasconsumption_hotwater' => $this->faker->numberBetween(200, 220), // Promedio ~210
            'gasconsumption_kitchen' => $this->faker->numberBetween(330, 370), // Alto, Promedio ~340
            'gasconsumption_price' => 0.50,
            'gasconsumption_status' => 1,
            'gasconsumption_userid' => 2,
        ];
    }
    
    /**
     * Define la secuencia para generar 30 fechas únicas (01/11/2025 - 30/11/2025) 
     * y aplica la lógica de fines de semana.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sequentialDates(): Factory
    {
        $startDate = Carbon::parse('2025-09-01');
        
        return $this->sequence(function (Sequence $sequence) use ($startDate) {
            
            // Genera la fecha secuencial (añade el índice de 0 a 29)
            $date = $startDate->clone()->addDays($sequence->index);
            $dayOfWeek = $date->format('N'); // 1=Lunes, 7=Domingo

            // Aplica la lógica de fin de semana (Viernes N=5, Sábado N=6, Domingo N=7)
            $cala = ($dayOfWeek >= 5) ? $this->faker->numberBetween(100, 250) : 0; 
            $velero = ($dayOfWeek >= 5) ? $this->faker->numberBetween(80, 200) : 0; 

            // Sobrescribe los campos de fecha y consumo condicional
            return [
                'gasconsumption_date' => $date->format('Y-m-d'),
                'gasconsumption_cala' => $cala,
                'gasconsumption_velero' => $velero,
            ];
        });
    }
}