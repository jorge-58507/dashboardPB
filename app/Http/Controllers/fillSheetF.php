<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange; // Importa ValueRange
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class fillSheetF extends Controller
{
    public function submitToSheet(Request $request)
    {
        
        // 1. Validar los datos del formulario (¡muy importante!)
        /*$validatedData = $request->validate([
            'total_directreservation_amount' => 'required|integer|max:255',
            'reservation_count' => 'required|integer|max:255',
            'total_directdaypass_amount' => 'required|integer|max:255',
            'directdaypass_count' => 'required|integer|max:255',
            'total_reservation_amount' => 'required|integer|max:255',
            'total_daypass_amount' => 'required|integer|max:255',
            'date' => 'required|string|max:255',
        ]);
        */

        $rules = [
            'total_directreservation_amount' => 'required|numeric',
            'reservation_count' => 'required|integer',
            'total_directdaypass_amount' => 'required|numeric',
            'directdaypass_count' => 'required|integer',
            'total_reservation_amount' => 'required|numeric',
            'total_daypass_amount' => 'required|numeric',
            'date' => 'required|string',
        ];

        // Usa Validator::make para obtener el validador manualmente
        $validator = Validator::make($request->all(), $rules);

        // Verifica si la validación falla
        if ($validator->fails()) {
            // Si falla, no redirijas, devuelve una respuesta JSON con los errores.
            // Esto te permitirá ver en Insomnia EXACTAMENTE por qué falló la validación.
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors() // Obtiene los mensajes de error
            ], 422); // Código de estado 422 indica errores de validación
        }

        // Si la validación pasa, $validator->fails() será falso,
        // y el código continuará desde aquí.
        // Puedes acceder a los datos validados si los necesitas, aunque $request->all()
        // ya contendría los datos de entrada originales que pasaron.
        // Si necesitas solo los datos validados, puedes usar $validator->validated()
        $validatedData = $validator->validated();


        // 2. Preparar los datos para Google Sheets
        // Asegúrate de que el orden de los elementos en este array
        // coincida con el orden de las columnas en tu Google Sheet.
        $min = 15;
        $max = 9999;
        $rowData = [
            mt_rand($min, $max),
            $validatedData['date'],//f2
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['total_reservation_amount'],//f5
            $validatedData['directdaypass_count'],//f6
            mt_rand($min, $max),
            $validatedData['total_directdaypass_amount'],//f8
            mt_rand($min, $max),
            $validatedData['total_daypass_amount'],//f10
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['reservation_count'],//f15
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['total_directreservation_amount'],//f21
            mt_rand($min, $max),
            date('Y-m-d H:i:s'), // Opcional: Añadir una marca de tiempo
            mt_rand($min, $max),
        ];
        
        // Los datos para la API deben ser un array de arrays (cada array interno es una fila)
        $values = [$rowData];
        //return $values;
        
        // 3. Conectar a Google Sheets API
        
         try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);

            $service = new Sheets($client);

            $spreadsheetId = config('google.sheet_id');
            // El rango define dónde buscar la primera fila vacía para añadir los datos.
            // 'f!A2' buscará a partir de la celda A2 en la pestaña 'f'.
            $range = 'f!A2'; // Asegúrate que 'f' es el nombre correcto de la pestaña

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            // 5. Manejar Respuesta de la API y Devolver Éxito (Devolver JSON)
            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                // Datos añadidos con éxito
                return response()->json(['message' => 'Información enviada a Google Sheets correctamente'], 200); // Código 200 para éxito
            } else {
                // La API no reportó filas actualizadas (podría ser un problema o la hoja vacía)
                Log::error('Failed to append row to Google Sheet, no rows updated', ['result' => $result]);
                return response()->json(['message' => 'Hubo un problema al enviar la información a Google Sheets (API no reportó actualización).'], 500); // Código 500 para error interno
            }

        } catch (\Exception $e) {
            // 6. Manejar Errores de Conexión o API (Devolver JSON)
            Log::error('Error sending data to Google Sheets: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Hubo un error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500); // Código 500 para error interno
        }
        
    }
}
