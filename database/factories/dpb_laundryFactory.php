<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\dpb_laundry;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon; // Necesario para manejar fechas secuenciales

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\dpb_laundry>
 */
class dpb_laundryFactory extends Factory
{
    protected $model = dpb_laundry::class;

    /**
     * Define el estado por defecto del modelo (valores fijos o aleatorios simples).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Los campos de fecha se establecerán en la secuencia
            'laundry_date' => null, // Fecha de registro (puede ser la misma que Init)
            'laundry_dateInit' => null, 
            'laundry_dateFinish' => null, 
            
            'laundry_total' => 0,  // Placeholder
            'laundry_cycle' => 0,  // Placeholder
            
            // Campos de valores fijos
            'laundry_userid' => 1,
            'laundry_status' => 1,
        ];
    }
    
    /**
     * Define la secuencia para generar 46 rangos de fechas, cubriendo 91 días 
     * (01/09/2025 al 30/11/2025).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sequentialRanges(): Factory
    {
        $startDate = Carbon::parse('2025-09-01');
        
        return $this->sequence(function (Sequence $sequence) use ($startDate) {
            
            // Calculamos el inicio y fin del rango
            $startRange = $startDate->clone()->addDays($sequence->index * 2);
            $endRange = $startRange->clone()->addDay();
            
            // El último registro (índice 45) solo tendrá 1 día, ya que son 91 días en total
            if ($sequence->index == 45) {
                $endRange = $startRange; // El último registro es solo el día 91
            }

            // Calculamos los días del rango para ajustar el consumo promedio (casi siempre 2 días)
            $daysInThisRange = $startRange->diffInDays($endRange) + 1; // 1 o 2 días

            // Lógica de Consumo (promedio: 25 ciclos/día, 42 por ciclo)
            
            // Ciclos: Promedio de 25 por día * Días en el rango, con una pequeña variación.
            $baseCycles = 25 * $daysInThisRange;
            $cycles = $this->faker->numberBetween($baseCycles - 5, $baseCycles + 5); 
            
            // Gasto Total: Ciclos * Costo por Ciclo (cercano a 42.00)
            $costPerCycle = 42.00;
            $totalCost = $cycles * $costPerCycle + $this->faker->randomFloat(2, -10, 10); // Variación de +/- 10

            return [
                'laundry_date' => $endRange->format('Y-m-d'), // Usamos la fecha de fin como fecha de registro
                'laundry_dateInit' => $startRange->format('Y-m-d'),
                'laundry_dateFinish' => $endRange->format('Y-m-d'),
                
                'laundry_cycle' => $cycles,
                'laundry_total' => round($totalCost, 2),
            ];
        });
    }
}