<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

// Importaciones para Google Sheets API
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest; 
use Google\Service\Sheets\Request as SheetRequest;       
use Google\Service\Sheets\DeleteDimensionRequest;        

class FormController extends Controller
{
    // CONTINUAR CON LA OPCION DE MOSTRAR LOS REGISTROS PARA PODER ELIMINARLOS
    /**
     * Muestra el formulario de ingreso de consumo de gas natural.
     */
    public function fillTable($sheetName,$rowData,$requestedDate,$userId)
    {
        $values = [$rowData]; // La API espera un array de arrays para las filas
        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');
            // Hoja de destino en Google Sheets.
            // Asegúrate de que esta hoja exista y se llame 'h'.
            //$sheetName = 'h'; // <--- REEMPLAZA 'h' con el nombre real de tu hoja de Google Sheets

            // Rango para leer: Asume que el user_id está en la columna A y la fechaRegistro en la columna C
            // Ajusta este rango si tus columnas para user_id y fechaRegistro están en otro lugar.
            $readRange = $sheetName . '!E:I'; 
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                // Ignora la fila de encabezados si existe (asume que la primera fila es de encabezados)
                $dataRows = array_slice($existingRows, 1);

                foreach ($dataRows as $row) {
                    // !IMPORTANTE!: Ajusta los índices [0] y [2] según la posición REAL
                    //              de user_id y fechaRegistro en tu Google Sheet.
                    $existingUserId = $row[0] ?? null; // Columna A (índice 0)
                    $existingDate = $row[4] ?? null;   // Columna C (índice 4)

                    // Compara el user_id y la fecha (asegúrate de que los tipos de datos coincidan si es necesario)
                    if ($existingUserId == $userId && $existingDate == $requestedDate) {
                        Log::warning('Intento de registro duplicado detectado.', [
                            'user_id' => $userId,
                            'fecha' => $requestedDate,
                        ]);
                        return ['message'=>'Ya existe un registro para esta fecha y usuario. No se permite duplicar.', 'HTTPcode' =>409];
                    }
                }
            }
            // --- FIN DE LA VERIFICACIÓN DE DUPLICADOS ---

            $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja 'h'.

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            // Manejo de la respuesta de la API y retorno de éxito/error
            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                return ['message'=>'Datos guardados con éxito.', 'HTTPcode' =>200];
            } else {
                Log::error('Fallo al añadir fila a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                return ['message'=>'Hubo un problema al guardar los datos.', 'HTTPcode' =>500];
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar datos: ' . $e->getMessage(), ['exception' => $e]);
            return ['message'=>'Error en el servidor al comunicarse con Google Sheets'.$e->getMessage(), 'HTTPcode' =>400];
        }
    }

    /**
     * Procesa el envío del formulario de consumo de gas y lo envía a Google Sheets.
     */
    public function getGasConsumptionFormPartial()
    {
        // Devuelve la vista Blade sin un layout completo
        return view('forms.gas_consumption_partial');
    }
    public function submitGasToSheet(Request $request)
    {
        $gas_price = 0.45;
        // 1. Definir y ejecutar la validación
        $rules = [
            'cala'          => 'required|numeric|max:999999999|min:0',
            'lavanderia'    => 'required|numeric|max:999999999|min:0',
            'cocina'        => 'required|numeric|max:999999999|min:0',
            'velero'        => 'required|numeric|max:999999999|min:0',
            'agua'          => 'required|numeric|max:999999999|min:0',
            'date'          => 'required|date|before_or_equal:today',
        ];

        // Usamos Validator::make() en lugar de $request->validate() para control manual de la respuesta JSON
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            // Si la validación falla, devuelve un JSON con los errores
            return response()->json([
                'success' => false,
                'message' => 'Falló la validación',
                'errors' => $validator->errors(),
            ], 422); // Código de estado 422 para errores de validación
        }

        $validatedData = $validator->validated(); // Obtener los datos validados

        // 2. Preparar los datos para Google Sheets
        // Asegúrate de que el orden de los elementos en este array
        // coincida con el orden de las columnas en tu Google Sheet.
        //Cala      f15/f1
        //Lavanderia f5/f12
        //Velero    f6/f19
        //Agua      f8/f22
        //Cocina    f21/f16
        $userId = Auth::id();
        $min = 15;
        $max = 9999;
        $rowData = [
            number_format($validatedData['cala']*$gas_price,2), //f1
            $validatedData['date'],//f2
            mt_rand($min, $max),
            mt_rand($min, max: $max),
            $validatedData['lavanderia'],//f5
            $validatedData['velero'],//f6
            mt_rand($min, $max),
            $validatedData['agua'],//f8
            mt_rand($min, $max),
            Auth::id(),
            mt_rand($min, $max),
            number_format($validatedData['lavanderia']*$gas_price,2), //f12
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['cala'],//f15
            number_format($validatedData['cocina']*$gas_price,2), //f16
            mt_rand($min, $max),
            mt_rand($min, $max),
            number_format($validatedData['velero']*$gas_price,2), //f19
            mt_rand($min, $max),
            $validatedData['cocina'],//f21
            number_format($validatedData['agua']*$gas_price,2), //f22
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
            $sheetName = 'f'; // <--- REEMPLAZA 'h' con el nombre real de tu hoja de Google Sheets

            // --- INICIO DE LA VERIFICACIÓN DE DUPLICADOS ---
            $requestedDate = $request->input('date'); // La fecha que el usuario intenta registrar

            // Rango para leer: Asume que el user_id está en la columna A y la fechaRegistro en la columna C
            // Ajusta este rango si tus columnas para user_id y fechaRegistro están en otro lugar.
            $readRange = $sheetName . '!B:J';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                // Ignora la fila de encabezados si existe (asume que la primera fila es de encabezados)
                $dataRows = array_slice($existingRows, 1);

                foreach ($dataRows as $row) {
                    // !IMPORTANTE!: Ajusta los índices [0] y [2] según la posición REAL
                    //              de user_id y fechaRegistro en tu Google Sheet.
                    $existingUserId = $row[8] ?? null;
                    $existingDate = $row[0] ?? null;

                    // Compara el user_id y la fecha (asegúrate de que los tipos de datos coincidan si es necesario)
                    if ($existingUserId == $userId && $existingDate == $requestedDate) {
                        Log::warning('Intento de registro duplicado de gas natural detectado.', [
                            'user_id' => $userId,
                            'fecha' => $requestedDate,
                        ]);
                        return response()->json([
                            'message' => 'Ya existe un registro para esta fecha y usuario. No se permite duplicar.'
                        ], 409); // 409 Conflict es un código HTTP apropiado para este error
                    }
                }
            }
            // --- FIN DE LA VERIFICACIÓN DE DUPLICADOS ---

            $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja 'h'.

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
                return response()->json(['message' => 'Información enviada correctamente'], 200); // Código 200 para éxito
            } else {
                // La API no reportó filas actualizadas (podría ser un problema o la hoja vacía)
                Log::error('Failed to append row to Google Sheet, no rows updated', ['result' => $result]);
                return response()->json(['message' => 'Hubo un problema al enviar la información.'], 500); // Código 500 para error interno
            }

        } catch (\Exception $e) {
            // 6. Manejar Errores de Conexión o API (Devolver JSON)
            Log::error('Error sending data: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Hubo un error en el servidor al comunicarse', 'error' => $e->getMessage()], 500); // Código 500 para error interno
        }

    }
    public function showGasConsumptionRecords()
    {
        $sheetName = 'f';
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11; // Límite de filas a mostrar

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        // ¡IMPORTANTE! Asegúrate de que tu modelo User tenga un método 'hasRole' o similar
        // Si no usas Spatie/Laravel-Permission, necesitarás otra forma de verificar si es admin.
        // Por ejemplo, si tienes una columna 'is_admin' en tu tabla de usuarios: $isAdmin = $currentUser->is_admin;
        $isAdmin = $currentUser->hasRole('Admin'); // Usando el método hasRole de Spatie/Laravel-Permission

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            // Siempre lee el rango completo para poder filtrar correctamente si no es admin.
            // Ajusta 'A:X' según la última columna de datos relevantes.
            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            $customHeadersMap = [
                1 => 'Fecha',     // Columna B (Timestamp de creación del registro)
                0 => '$.Cala',     // Columna A (ID del usuario que creó el registro)
                11 => '$.Lavanderia ',  // Columna L
                15 => '$.Cocina',    // Columna P
                18 => '$.Velero',     // Columna S
                21 => '$.Agua Caliente',// Columna V
                4 => 'Lavanderia',   // Columna E
                5 => 'Velero',     // Columna F
                7 => 'Agua', // Columna H
                9 => 'Usuario', // Columna J
                14 => 'Cala',  // Columna O
                20 => 'Cocina',// Columna U
                22 => 'Creación',    // Columna W
            ];
            // Construye los encabezados a mostrar basándose en el mapeo
            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                $headerRow = array_shift($values); // Remueve la primera fila (encabezados de la hoja)
                $filteredRows = [];
                $values = array_reverse($values);
                // Iterar sobre las filas leídas (desde la fila 2 en adelante) para aplicar el filtro de usuario
                foreach ($values as $index => $row) {
                    // El ID de usuario está en la primera columna (índice 0)
                    $rowUserId = $row[0] ?? null; // Obtener el ID de usuario de la fila

                    // Si es administrador O el ID de usuario de la fila coincide con el usuario actual
                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        // Añadir el número de fila real de Google Sheets (importante para eliminación)
                        // El +2 es porque los datos empiezan en la fila 2 (después de encabezado)
                        // y el $values array es 0-indexado desde la primera fila de datos.
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        // Mapea los datos de la fila de Google Sheets a la estructura esperada por la vista
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                // Después de filtrar, aplicamos el límite de 11 filas (las más recientes)
                // Si el usuario es administrador, verá las últimas 11 de *todos* los registros.
                // Si no es administrador, verá las últimas 11 de *sus propios* registros.
                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.gasConsumption_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Inventario HK: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteGasConsumption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'f'; // <--- ¡Confirma que 'gas' es el nombre correcto!

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }

            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber
                ]
            ]);


            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito de Consumo de Gas:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Consumo de Gas eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila de Consumo de Gas, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro de Consumo de Gas.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Consumo de Gas: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }

    protected $laundryHeadersMap = [
        16 => 'Creación',           
        4 => 'Usuario',           
        8 => 'Fecha Inicio',      
        20 => 'Fecha Final',      
        14 => 'Total Gasto',      
        19 => 'Cantidad de Ciclos',
    ];
    public function showLaundryForm()
    {
        return view('forms.laundry_partial');
    }
    public function submitLaundry(Request $request) // <-- FUNCIÓN RENOMBRADA
    {
        // ACTUALIZACIÓN: Reglas de validación para fechaInicio y fechaFin
        $validator = Validator::make($request->all(), [
            'totalGasto'        => ['required', 'numeric', 'min:0'],
            'cantidadCiclos'    => ['required', 'integer', 'min:0'],
            'fechaInicio'       => ['required', 'date'], // NUEVA REGLA
            'fechaFin'          => ['required', 'date', 'after_or_equal:fechaInicio', 'before_or_equal:today'],
        ], [
            'totalGasto.required' => 'El campo Gasto Total es obligatorio.',
            'totalGasto.numeric' => 'El campo Gasto Total debe ser un número.',
            'totalGasto.min' => 'El campo Gasto Total no puede ser negativo.',
            'cantidadCiclos.required' => 'El campo Cantidad de Ciclos es obligatorio.',
            'cantidadCiclos.integer' => 'El campo Cantidad de Ciclos debe ser un número entero.',
            'cantidadCiclos.min' => 'El campo Cantidad de Ciclos no puede ser negativo.',
            'fechaInicio.required' => 'El campo Fecha de Inicio es obligatorio.', // NUEVO MENSAJE
            'fechaInicio.date' => 'El campo Fecha de Inicio debe ser una fecha válida.', // NUEVO MENSAJE
            'fechaFin.required' => 'El campo Fecha de Fin es obligatorio.', // MENSAJE ACTUALIZADO
            'fechaFin.date' => 'El campo Fecha de Fin debe ser una fecha válida.', // MENSAJE ACTUALIZADO
            'fechaFin.after_or_equal' => 'La Fecha de Fin debe ser igual o posterior a la Fecha de Inicio.', // NUEVO MENSAJE
            'fechaFin.before_or_equal' => 'La Fecha de Fin no puede ser mayor a la fecha actual.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $min = 15;
        $max = 9999;

        // Asegúrate de ajustar los índices según la estructura de tu hoja 'g'
        $rowData = [
            mt_rand($min, $max), // Columna 1
            mt_rand($min, $max), // Columna 2
            mt_rand($min, $max), // Columna 3
            mt_rand($min, $max), // Columna 4
            Auth::id(),          // Columna 5 (user_id)
            mt_rand($min, $max), // Columna 6
            mt_rand($min, $max), // Columna 7
            mt_rand($min, $max), // Columna 8
            $request->input('fechaInicio'), // <-- NUEVO CAMPO: Fecha de Inicio (columna 9)
            mt_rand($min, $max), // Columna 10
            mt_rand($min, $max), // Columna 11
            mt_rand($min, $max), // Columna 12
            mt_rand($min, $max), // Columna 13
            mt_rand($min, $max), // Columna 14
            $request->input('totalGasto'), // Columna 15 (totalGasto)
            mt_rand($min, $max), // Columna 16
            date('Y-m-d H:i:s'), // Columna 17 (Timestamp)
            mt_rand($min, $max), // Columna 18
            mt_rand($min, $max), // Columna 19
            $request->input('cantidadCiclos'), // Columna 20 (cantidadCiclos)
            $request->input('fechaFin'), // <-- NUEVO CAMPO: Fecha de Fin (columna 21)
            mt_rand($min, $max), // Columna 22
            mt_rand($min, $max), // Columna 23
            mt_rand($min, $max), // Columna 24
        ];

        $values = [$rowData];
        
        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);

            $service = new Sheets($client);

            $spreadsheetId = config('google.sheet_id');
            // El rango define dónde buscar la primera fila vacía para añadir los datos.
            // 'f!A2' buscará a partir de la celda A2 en la pestaña 'f'.
            $range = 'g!A2'; // Asegúrate que 'f' es el nombre correcto de la pestaña

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
                return response()->json(['message' => 'Información enviada correctamente'], 200); // Código 200 para éxito
            } else {
                // La API no reportó filas actualizadas (podría ser un problema o la hoja vacía)
                Log::error('Failed to append row to Google Sheet, no rows updated', ['result' => $result]);
                return response()->json(['message' => 'Hubo un problema al enviar la información.'], 500); // Código 500 para error interno
            }

        } catch (\Exception $e) {
            // 6. Manejar Errores de Conexión o API (Devolver JSON)
            Log::error('Error sending data: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Hubo un error en el servidor al comunicarse', 'error' => $e->getMessage()], 500); // Código 500 para error interno
        }

    }
    public function showLaundryRecords()
    {
        // ¡IMPORTANTE! Confirma que 'laundry' es el nombre exacto de tu hoja de Lavandería
        $sheetName = 'G';
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11;

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin'); // O tu método para verificar admin

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            // Ajusta 'A:M' si tus datos de lavandería ocupan más o menos columnas.
            // La 'M' corresponde al índice 12 (Bolsa #10).
            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            // Usa el mapeo de encabezados específico para lavandería
            $customHeadersMap = $this->laundryHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
                $values = array_reverse($values);

                foreach ($values as $index => $row) {
                    $rowUserId = $row[0] ?? null; // Asume user_id está en la primera columna (índice 0)

                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.laundry_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Lavandería: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteLaundry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'g';

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }

            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber
                ]
            ]);

            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([ // Usar el nombre de clase completo
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito de Lavandería:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Lavandería eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila de Lavandería, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro de Lavandería.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Lavandería: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }


    protected $salesHeadersMap = [
        16 => 'Creación',          
        8 => 'Fecha',         
        4 => 'Usuario',       
        14 => '$ Total',     
        20 => '$ OtrosIngresos',
        18 => 'Clientes',       
    ];
    public function showSalesForm()
    {
        return view('forms.sales_partial');
    }
    public function submitSales(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'montoWeb'              => ['required', 'numeric', 'min:0'],
            'montoCallcenter'       => ['required', 'numeric', 'min:0'],
            'montoOTA'              => ['required', 'numeric', 'min:0'],
            'montoCorporativo'      => ['required', 'numeric', 'min:0'],
            'montoAgenciaNac'       => ['required', 'numeric', 'min:0'],
            'montoAgenciaInt'       => ['required', 'numeric', 'min:0'],
            'montoArenas'           => ['required', 'numeric', 'min:0'],
            'fechaRegistro'         => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
        ], [
            'montoWeb.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoWeb.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoWeb.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoCallcenter.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoCallcenter.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoCallcenter.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoOTA.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoOTA.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoOTA.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoCorporativo.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoCorporativo.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoCorporativo.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoAgenciaNac.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoAgenciaNac.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoAgenciaNac.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoAgenciaInt.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoAgenciaInt.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoAgenciaInt.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoArenas.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoArenas.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoArenas.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'fechaRegistro.required' => 'El campo Fecha del Registro es obligatorio.',
            'fechaRegistro.date' => 'El campo Fecha del Registro debe ser una fecha válida.',
            'fechaRegistro.before_or_equal' => 'La Fecha del Registro no puede ser mayor a la fecha actual.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $min = 15;
        $max = 9999;
        $userId = Auth::id(); // ID del usuario autenticado

        // Construcción de la fila de datos con 24 columnas
        // ¡IMPORTANTE! Ajusta los índices (0-23) para que tus datos reales
        // (user_id, montoTotalHospedaje, cantidadClienteHospedado, totalVentaOtroIngreso, fechaRegistro)
        // caigan en las columnas correctas en tu hoja 'h'.

        $rowData = [
            mt_rand($min, $max), // Columna 1 (índice 0)
            floatval($request->input('montoCorporativo')),     // Columna 2 (índice 1)
            mt_rand($min, $max), // Columna 3 (índice 2)
            floatval($request->input('montoAgenciaNac')),     // Columna 4 (índice 3)
            mt_rand($min, $max), // Columna 5 (índice 4)
            mt_rand($min, $max), // Columna 6 (índice 5)
            $userId,             // Columna 7 (índice 6)
            mt_rand($min, $max), // Columna 8 (índice 7)
            floatval($request->input('montoCallcenter')),     // Columna 9 (índice 8)
            mt_rand($min, $max), // Columna 10 (índice 9)
            floatval($request->input('montoOTA')),            // Columna 11 (índice 10)
            mt_rand($min, $max), // Columna 12 (índice 11)
            floatval($request->input('montoAgenciaInt')),     // Columna 13 (índice 12)
            floatval($request->input('montoArenas')),         // Columna 14 (índice 13)
            mt_rand($min, $max), // Columna 15 (índice 14)
            mt_rand($min, $max), // Columna 16 (índice 15)
            now()->toDateTimeString(),              // Columna 17 (índice 16) - timestamp_insercion
            mt_rand($min, $max), // Columna 18 (índice 17)
            mt_rand($min, $max), // Columna 19 (índice 18)
            floatval($request->input('montoWeb')),         // Columna 20 (índice 19)
            mt_rand($min, $max), // Columna 21 (índice 20)
            mt_rand($min, $max), // Columna 22 (índice 21)
            mt_rand($min, $max), // Columna 23 (índice 22)
            $request->input('fechaRegistro'),     // Columna 24 (índice 23) - fechaRegistro
        ];
        $ans = $this->fillTable('h',$rowData,$request->input('fechaRegistro'),$userId);

        return response()->json([
            'message' => $ans['message']
        ], $ans['HTTPcode']);
    }
    public function showSalesRecords()
    {
        // ¡IMPORTANTE! Confirma que 'Sales' es el nombre exacto de tu hoja de Ventas
        $sheetName = 'h';
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11;

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin'); // O tu método para verificar admin

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            // Usa el mapeo de encabezados específico para ventas
            $customHeadersMap = $this->salesHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
                $values = array_reverse($values);

                foreach ($values as $index => $row) {
                    $rowUserId = $row[0] ?? null; // Asume user_id está en la primera columna (índice 0)

                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.sales_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Ventas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'h';

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }

            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber
                ]
            ]);

            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([ // Usar el nombre de clase completo
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito de Ventas:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Ventas eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila de Ventas, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro de Ventas.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Ventas: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }



    protected $auditorHeadersMap = [
        0 => 'AB',          
        1 => 'Otro',         
        2 => 'Usuario',       
        3 => 'Creacion',     
        4 => 'Fecha',       
    ];
    public function showAuditorForm()
    {
        return view('forms.auditor_partial');
    }
    public function submitAuditor(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'montoAB'               => ['required', 'numeric', 'min:0'],
            'montoOtro'             => ['required', 'numeric', 'min:0'],
            'fechaRegistro'         => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
        ], [
            'montoAB.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoAB.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoAB.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'montoOtro.required' => 'El campo Monto Total Ventas Hospedaje es obligatorio.',
            'montoOtro.numeric' => 'El campo Monto Total Ventas Hospedaje debe ser un número.',
            'montoOtro.min' => 'El campo Monto Total Ventas Hospedaje no puede ser negativo.',
            'fechaRegistro.required' => 'El campo Fecha del Registro es obligatorio.',
            'fechaRegistro.date' => 'El campo Fecha del Registro debe ser una fecha válida.',
            'fechaRegistro.before_or_equal' => 'La Fecha del Registro no puede ser mayor a la fecha actual.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $min = 15;
        $max = 9999;
        $userId = Auth::id(); // ID del usuario autenticado

        $rowData = [
            //            mt_rand($min, $max), // Columna 1 (índice 0)
            floatval($request->input('montoAB')),     // Columna 2 (índice 1)
            //            mt_rand($min, $max), // Columna 3 (índice 2)
            floatval($request->input('montoOtro')),     // Columna 4 (índice 3)
            //            mt_rand($min, $max), // Columna 5 (índice 4)
            //            mt_rand($min, $max), // Columna 6 (índice 5)
            $userId,             // Columna 7 (índice 6)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 10 (índice 9)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 12 (índice 11)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 15 (índice 14)
            //            mt_rand($min, $max), // Columna 16 (índice 15)
            now()->toDateTimeString(),              // Columna 17 (índice 16) - timestamp_insercion
            //            mt_rand($min, $max), // Columna 18 (índice 17)
            //            mt_rand($min, $max), // Columna 19 (índice 18)
            //            mt_rand($min, $max), // Columna 8 (índice 7)
            //            mt_rand($min, $max), // Columna 21 (índice 20)
            //            mt_rand($min, $max), // Columna 22 (índice 21)
            //            mt_rand($min, $max), // Columna 23 (índice 22)
            $request->input('fechaRegistro'),     // Columna 24 (índice 23) - fechaRegistro
        ];
        $ans = $this->fillTable('l',$rowData,$request->input('fechaRegistro'),$userId);

        return response()->json([
            'message' => $ans['message']
        ], 200);
    }
    public function showAuditorRecords()
    {
        // ¡IMPORTANTE! Confirma que 'Sales' es el nombre exacto de tu hoja de Ventas
        $sheetName = 'h';
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11;

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin'); // O tu método para verificar admin

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            // Usa el mapeo de encabezados específico para ventas
            $customHeadersMap = $this->salesHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
                $values = array_reverse($values);

                foreach ($values as $index => $row) {
                    $rowUserId = $row[0] ?? null; // Asume user_id está en la primera columna (índice 0)

                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.sales_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Ventas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteAuditor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'h';

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }

            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber
                ]
            ]);

            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([ // Usar el nombre de clase completo
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito de Ventas:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Ventas eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila de Ventas, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro de Ventas.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Ventas: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }



    protected $phoneCallHeadersMap = [
        3 => 'Usuario',         
        4 => 'Fecha',         
        6 => 'Cantidad',   
        8 => 'Ventas Logradas', 
        11 => 'Tiempo Prom',    
        13 => 'Creación',       
    ];
    public function showPhoneCallForm()
    {
        return view('forms.phonecall_partial');
    }
    public function submitPhoneCall(Request $request)
    {
        // 1. Validación de los datos
        $validator = Validator::make($request->all(), [
            'fechaRegistro'             => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
            'cantidadLlamadas'          => ['required', 'integer', 'min:0'],
            'ventasLogradas'            => ['required', 'integer', 'min:0'],
            'tiempoPromedioLlamadas'    => ['required', 'numeric', 'min:0'],
        ], [
            'fechaRegistro.required' => 'La fecha es obligatoria.',
            'fechaRegistro.date_format' => 'El formato de la fecha no es válido (debe ser AAAA-MM-DD).',
            'fechaRegistro.before_or_equal' => 'La fecha de registro no puede ser futura.',
            'cantidadLlamadas.required' => 'La cantidad de llamadas es obligatoria.',
            'cantidadLlamadas.integer' => 'La cantidad de llamadas debe ser un número entero.',
            'cantidadLlamadas.min' => 'La cantidad de llamadas no puede ser negativa.',
            'ventasLogradas.required' => 'La cantidad de ventas logradas es obligatoria.',
            'ventasLogradas.integer' => 'Las ventas logradas deben ser un número entero.',
            'ventasLogradas.min' => 'Las ventas logradas no pueden ser negativas.',
            'tiempoPromedioLlamadas.required' => 'El tiempo promedio de llamadas es obligatorio.',
            'tiempoPromedioLlamadas.numeric' => 'El tiempo promedio de llamadas debe ser un número.',
            'tiempoPromedioLlamadas.min' => 'El tiempo promedio de llamadas no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $min = 15; // Valores de ejemplo para columnas de relleno
        $max = 9999;
        $userId = Auth::id(); // ID del usuario autenticado
        $timestampInsercion = Carbon::now()->toDateTimeString(); // Timestamp de la inserción

        // Construcción de la fila de datos con 24 columnas (ajusta esto según tus necesidades exactas)
        // !IMPORTANTE!: Debes mapear los índices de este array a las columnas REALES de tu hoja 'i'.
        //              Los `mt_rand` son placeholders.
        $rowData = [
            mt_rand($min, $max), // Columna 1 (índice 0)
            mt_rand($min, $max), // Columna 2 (índice 1)
            mt_rand($min, $max), // Columna 3 (índice 2)
            $userId,                       // Columna 4 (índice 3) - user_id
            $request->input('fechaRegistro'),           // Columna 5 (índice 4) - fechaRegistro
            mt_rand($min, $max), // Columna 6 (índice 5)
            floatval($request->input('cantidadLlamadas')),        // Columna 7 (índice 6) - cantidadLlamadas
            mt_rand($min, $max), // Columna 8 (índice 7)
            floatval($request->input('ventasLogradas')),          // Columna 9 (índice 8) - ventasLogradas
            mt_rand($min, $max), // Columna 10 (índice 9)
            mt_rand($min, $max), // Columna 11 (índice 10)
            floatval($request->input('tiempoPromedioLlamadas')), // Columna 12 (índice 11) - tiempoPromedioLlamadas
            mt_rand($min, $max), // Columna 13 (índice 12)
            $timestampInsercion, // Columna 14 (índice 13) - timestamp_insercion
            mt_rand($min, $max), // Columna 15 (índice 14)
            mt_rand($min, $max), // Columna 16 (índice 15)
            mt_rand($min, $max), // Columna 17 (índice 16)
            mt_rand($min, $max), // Columna 18 (índice 17)
            mt_rand($min, $max), // Columna 19 (índice 18)
            mt_rand($min, $max), // Columna 20 (índice 19)
            mt_rand($min, $max), // Columna 21 (índice 20)
            mt_rand($min, $max), // Columna 22 (índice 21)
            mt_rand($min, $max), // Columna 23 (índice 22)
            mt_rand($min, $max), // Columna 24 (índice 23)
        ];

        $ans = $this->fillTable('j',$rowData,$request->input('fechaRegistro'),$userId);

        return response()->json([
            'message' => $ans['message']
        ], $ans['HTTPcode']);
        /*
        $values = [$rowData]; // La API espera un array de arrays para las filas

        try {
            // Configuración y envío a Google Sheets (usando la misma lógica que submitSales)
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');
            // Hoja de destino en Google Sheets para llamadas
            // ¡IMPORTANTE! Asegúrate de que esta hoja exista y se llame 'i'.
            $sheetName = 'j'; // <--- REEMPLAZA 'j' con el nombre real de tu hoja de Google Sheets para Inventario HK

            // --- INICIO DE LA VERIFICACIÓN DE DUPLICADOS ---
            $requestedDate = $request->input('fechaRegistro'); // La fecha que el usuario intenta registrar

            // Rango para leer: Asume que el user_id está en la columna A y la fechaRegistro en la columna C
            // Ajusta este rango si tus columnas para user_id y fechaRegistro están en otro lugar.
            $readRange = $sheetName . '!D:E'; // Lee user_id (Col A), timestamp_insercion (Col B), fechaRegistro (Col C)
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                // Ignora la fila de encabezados si existe (asume que la primera fila es de encabezados)
                $dataRows = array_slice($existingRows, 1);

                foreach ($dataRows as $row) {
                    // !IMPORTANTE!: Ajusta los índices [0] y [2] según la posición REAL
                    //              de user_id y fechaRegistro en tu Google Sheet.
                    $existingUserId = $row[0] ?? null; // Columna A (índice 0)
                    $existingDate = $row[1] ?? null;   // Columna C (índice 2)

                    // Compara el user_id y la fecha (asegúrate de que los tipos de datos coincidan si es necesario)
                    if ($existingUserId == $userId && $existingDate == $requestedDate) {
                        Log::warning('Intento de registro duplicado de llamadas detectado.', [
                            'user_id' => $userId,
                            'fecha' => $requestedDate,
                        ]);
                        return response()->json([
                            'message' => 'Ya existe un registro para esta fecha y usuario. No se permite duplicar.'
                        ], 409); // 409 Conflict es un código HTTP apropiado para este error
                    }
                }
            }
            // --- FIN DE LA VERIFICACIÓN DE DUPLICADOS ---


            $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja definida.

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            // Manejo de la respuesta de la API y retorno de éxito/error
            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                Log::info('Rendimiento de llamadas registrado:', $request->all());
                return response()->json(['message' => 'Rendimiento de llamadas registrado.'], 200);
            } else {
                Log::error('Fallo al añadir fila de registro de llamadas a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                return response()->json(['message' => 'Hubo un problema al guardar el reporte diario de llamadas.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar datos de registro de llamadas: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
        */
    }
    public function showPhoneCallRecords()
    {
        // ¡IMPORTANTE! Confirma que 'Phone Call' es el nombre exacto de tu hoja de Llamadas
        $sheetName = 'j';
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11;

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin'); // O tu método para verificar admin

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            // Ajusta 'A:G' si tus datos de llamadas ocupan más o menos columnas.
            // La 'G' corresponde al índice 6 (Comentarios).
            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            // Usa el mapeo de encabezados específico para llamadas
            $customHeadersMap = $this->phoneCallHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
                $values = array_reverse($values);

                foreach ($values as $index => $row) {
                    $rowUserId = $row[0] ?? null; // Asume user_id está en la primera columna (índice 0)

                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.phonecall_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Llamadas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deletePhoneCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'j';

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }

            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber
                ]
            ]);

            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([ // Usar el nombre de clase completo
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito de Llamadas:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Llamadas eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila de Llamadas, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro de Llamadas.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Llamadas: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }



    
    public function showInventoryHkForm()
    {
        return view('forms.inventoryhk_partial');
    }
    public function submitInventoryHk(Request $request)
    {
        // 1. Validación de los datos
        $validator = Validator::make($request->all(), [
            'fechaInventario'       => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
            'sheet_k'               => ['nullable', 'integer', 'min:0'],
            'sheet_q'               => ['nullable', 'integer', 'min:0'],
            'pillowcase_k'          => ['nullable', 'integer', 'min:0'],
            'pillowcase_q'          => ['nullable', 'integer', 'min:0'],
            'pillow_k'              => ['nullable', 'integer', 'min:0'],
            'pillow_q'              => ['nullable', 'integer', 'min:0'],
            'mattressprotector_k'   => ['nullable', 'integer', 'min:0'],
            'mattressprotector_q'   => ['nullable', 'integer', 'min:0'],
            'towel_blank'           => ['nullable', 'integer', 'min:0'],
            'hand_towel'            => ['nullable', 'integer', 'min:0'],
            'foot_towel'            => ['nullable', 'integer', 'min:0'],
            'face_towel'            => ['nullable', 'integer', 'min:0'],
            'towel_blue'            => ['nullable', 'integer', 'min:0'],
            'blanket_blue'          => ['nullable', 'integer', 'min:0'],
            'blanket_green'         => ['nullable', 'integer', 'min:0'],
            'duveth_k'              => ['nullable', 'integer', 'min:0'],
            'duveth_q'              => ['nullable', 'integer', 'min:0'],
            'cover_k'               => ['nullable', 'integer', 'min:0'],
            'cover_q'               => ['nullable', 'integer', 'min:0'],
            'bedskirt_k'            => ['nullable', 'integer', 'min:0'],
            'bedskirt_q'            => ['nullable', 'integer', 'min:0'],
        ], [
            'fechaInventario.required' => 'La fecha del inventario es obligatoria.',
            'fechaInventario.date_format' => 'El formato de la fecha no es válido (debe ser AAAA-MM-DD).',
            'fechaInventario.before_or_equal' => 'La fecha del inventario no puede ser futura.',
            // Mensajes genéricos para los campos numéricos. Puedes personalizar si es necesario.
            '*.integer' => 'El campo :attribute debe ser un número entero.',
            '*.min' => 'El campo :attribute no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Datos comunes
        $userId = Auth::id(); // ID del usuario autenticado
        $timestampInsercion = Carbon::now()->toDateTimeString(); // Timestamp de la inserción

            // !IMPORTANTE!: Configura este array '$rowData' para que el orden de los datos
            //              coincida con las columnas exactas en tu hoja de Google Sheets para Inventario HK.
            //              Si tienes columnas de relleno, usa 'mt_rand()' o déjalas vacías según necesites.
        $rowData = [
            $userId,                                     // Columna A (ej. user_id)
            $timestampInsercion,                         // Columna B (ej. timestamp de inserción)
            $request->input('fechaInventario'),          // Columna C (ej. Fecha del Inventario)
            (int) $request->input('sheet_k', 0),          // Columna D (ej. sheet_k). Usamos (int) y 0 por defecto.
            (int) $request->input('sheet_q', 0),          // Columna E (ej. sheet_q)
            (int) $request->input('pillowcase_k', 0),     // Columna F (ej. pillowcase_k)
            (int) $request->input('pillowcase_q', 0),     // Columna G (ej. pillowcase_q)
            (int) $request->input('pillow_k', 0),         // Columna H (ej. pillow_k)
            (int) $request->input('pillow_q', 0),         // Columna I (ej. pillow_q)
            (int) $request->input('mattressprotector_k', 0), // Columna J (ej. mattressprotector_k)
            (int) $request->input('mattressprotector_q', 0), // Columna K (ej. mattressprotector_q)
            (int) $request->input('towel_blank', 0),      // Columna L (ej. towel_blank)
            (int) $request->input('hand_towel', 0),       // Columna M (ej. hand_towel)
            (int) $request->input('foot_towel', 0),       // Columna N (ej. foot_towel)
            (int) $request->input('face_towel', 0),       // Columna O (ej. face_towel)
            (int) $request->input('towel_blue', 0),       // Columna P (ej. towel_blue)
            (int) $request->input('blanket_blue', 0),     // Columna Q (ej. blanket_blue)
            (int) $request->input('blanket_green', 0),    // Columna R (ej. blanket_green)
            (int) $request->input('duveth_k', 0),         // Columna S (ej. duveth_k)
            (int) $request->input('duveth_q', 0),         // Columna T (ej. duveth_q)
            (int) $request->input('cover_k', 0),          // Columna U (ej. cover_k)
            (int) $request->input('cover_q', 0),          // Columna V (ej. cover_q)
            (int) $request->input('bedskirt_k', 0),       // Columna W (ej. bedskirt_k)
            (int) $request->input('bedskirt_q', 0),       // Columna X (ej. bedskirt_q)
        ];
        $values = [$rowData]; // La API espera un array de arrays para las filas

        try {
            // Configuración y envío a Google Sheets
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');
            // Hoja de destino en Google Sheets para Inventario HK
            // ¡IMPORTANTE! Asegúrate de que esta hoja exista y tenga el nombre correcto.
            $sheetName = 'k'; // <--- REEMPLAZA 'j' con el nombre real de tu hoja de Google Sheets para Inventario HK

            // --- INICIO DE LA VERIFICACIÓN DE DUPLICADOS ---
            $requestedDate = $request->input('fechaInventario'); // La fecha que el usuario intenta registrar

            // Rango para leer: Asume que el user_id está en la columna A y la fechaInventario en la columna C
            // Ajusta este rango si tus columnas para user_id y fechaInventario están en otro lugar.
            $readRange = $sheetName . '!A:C'; // Lee user_id (Col A), timestamp_insercion (Col B), fechaInventario (Col C)
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                // Ignora la fila de encabezados si existe (asume que la primera fila es de encabezados)
                $dataRows = array_slice($existingRows, 1);

                foreach ($dataRows as $row) {
                    // !IMPORTANTE!: Ajusta los índices [0] y [2] según la posición REAL
                    //              de user_id y fechaInventario en tu Google Sheet.
                    $existingUserId = $row[0] ?? null; // Columna A (índice 0)
                    $existingDate = $row[2] ?? null;   // Columna C (índice 2)

                    // Compara el user_id y la fecha (asegúrate de que los tipos de datos coincidan si es necesario)
                    if ($existingUserId == $userId && $existingDate == $requestedDate) {
                        Log::warning('Intento de registro duplicado de Inventario HK detectado.', [
                            'user_id' => $userId,
                            'fecha' => $requestedDate,
                        ]);
                        return response()->json([
                            'message' => 'Ya existe un registro de inventario para esta fecha y usuario. No se permite duplicar.'
                        ], 409); // 409 Conflict es un código HTTP apropiado para este error
                    }
                }
            }
            // --- FIN DE LA VERIFICACIÓN DE DUPLICADOS ---


            $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja definida.

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW' // 'RAW' para que Google Sheets interprete el tipo de dato
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            // Manejo de la respuesta de la API y retorno de éxito/error
            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                Log::info('Datos de Inventario HK guardados con éxito:', $request->all());
                return response()->json(['message' => 'Inventario HK registrado exitosamente.'], 200);
            } else {
                Log::error('Fallo al añadir fila de Inventario HK a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                return response()->json(['message' => 'Hubo un problema al guardar el Inventario HK.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar datos de Inventario HK: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }
    public function showInventoryHkRecords()
    {
        $sheetName = 'k'; // ¡IMPORTANTE! Confirma que 'j' es el nombre exacto de tu hoja de Inventario HK
        $spreadsheetId = config('google.sheet_id');
        $limitRows = 11; // Límite de filas a mostrar

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        // ¡IMPORTANTE! Asegúrate de que tu modelo User tenga un método 'hasRole' o similar
        // Si no usas Spatie/Laravel-Permission, necesitarás otra forma de verificar si es admin.
        // Por ejemplo, si tienes una columna 'is_admin' en tu tabla de usuarios: $isAdmin = $currentUser->is_admin;
        $isAdmin = $currentUser->hasRole('Admin'); // Usando el método hasRole de Spatie/Laravel-Permission

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS_READONLY);
            $service = new Sheets($client);

            // Siempre lee el rango completo para poder filtrar correctamente si no es admin.
            // Ajusta 'A:X' según la última columna de datos relevantes.
            $fullRange = $sheetName . '!A:X';
            $response = $service->spreadsheets_values->get($spreadsheetId, $fullRange);
            $values = $response->getValues();

            $displayHeaders = [];
            $records = [];

            $customHeadersMap = [
                0 => 'ID Usuario',     // Columna A (ID del usuario que creó el registro)
                1 => 'Fecha/Hora',     // Columna B (Timestamp de creación del registro)
                2 => 'Fecha Inventario',// Columna C (Fecha del inventario)
                3 => 'Sábana King',    // Columna D
                4 => 'Sábana Queen',   // Columna E
                5 => 'Funda King',     // Columna F
                6 => 'Funda Queen',    // Columna G
                7 => 'Almohadas King', // Columna H
                8 => 'Almohadas Queen',// Columna I
                9 => 'Protector King', // Columna J
                10 => 'Protector Queen',// Columna K
                11 => 'Toalla Blanca',  // Columna L
                12 => 'Toalla P/Mano',  // Columna M
                13 => 'Toalla P/Pie',   // Columna N
                14 => 'Toalla Facial',  // Columna O
                15 => 'Toalla Azul',    // Columna P
                16 => 'Frazada Azul',   // Columna Q
                17 => 'Frazada Verde',  // Columna R
                18 => 'Duvet King',     // Columna S
                19 => 'Duvet Queen',    // Columna T
                20 => 'Cubre Colchón King',// Columna U
                21 => 'Cubre Colchón Queen',// Columna V
                22 => 'Faldón King',    // Columna W
                23 => 'Faldón Queen',   // Columna X
            ];

            // Construye los encabezados a mostrar basándose en el mapeo
            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                $headerRow = array_shift($values); // Remueve la primera fila (encabezados de la hoja)
                $filteredRows = [];

                // Iterar sobre las filas leídas (desde la fila 2 en adelante) para aplicar el filtro de usuario
                $values = array_reverse($values);
                foreach ($values as $index => $row) {
                    // El ID de usuario está en la primera columna (índice 0)
                    $rowUserId = $row[0] ?? null; // Obtener el ID de usuario de la fila

                    // Si es administrador O el ID de usuario de la fila coincide con el usuario actual
                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        // Añadir el número de fila real de Google Sheets (importante para eliminación)
                        // El +2 es porque los datos empiezan en la fila 2 (después de encabezado)
                        // y el $values array es 0-indexado desde la primera fila de datos.
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        // Mapea los datos de la fila de Google Sheets a la estructura esperada por la vista
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                // Después de filtrar, aplicamos el límite de 11 filas (las más recientes)
                // Si el usuario es administrador, verá las últimas 11 de *todos* los registros.
                // Si no es administrador, verá las últimas 11 de *sus propios* registros.
                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.inventoryhk_records', compact('displayHeaders', 'records'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Inventario HK: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteInventoryHk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:2'], // Mínimo 2 porque la fila 1 son encabezados
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rowNumber = $request->input('row_number');
        $sheetName = 'k'; 

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS); // Necesita permisos de escritura/edición
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            // --- Obtener el sheetId de la hoja por su nombre ---
            // Esto es necesario para la operación deleteDimension
            $targetSheetId = null;
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $targetSheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            if ($targetSheetId === null) {
                throw new \Exception("La hoja '{$sheetName}' no fue encontrada en el Spreadsheet para eliminación.");
            }
            // --- FIN de Obtener sheetId ---

            // Crear la solicitud para eliminar la fila
            $deleteRequest = new DeleteDimensionRequest([
                'range' => [
                    'sheetId' => $targetSheetId, // Usamos el ID de la hoja obtenido
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1, // La API es 0-indexada, si es fila 2, startIndex es 1
                    'endIndex' => $rowNumber        // endIndex es exclusiva, si startIndex es 1, queremos borrar hasta el índice 2 (fila 2)
                ]
            ]);

            $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new SheetRequest([
                        'deleteDimension' => $deleteRequest
                    ])
                ]
            ]);

            $result = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

            if ($result->getReplies() && count($result->getReplies()) > 0) {
                Log::info('Fila eliminada con éxito del Inventario HK:', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Registro de Inventario HK eliminado exitosamente.'], 200);
            } else {
                Log::error('Fallo al eliminar fila del Inventario HK, no se obtuvo respuesta exitosa.', ['row_number' => $rowNumber, 'sheet' => $sheetName, 'result' => $result, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Hubo un problema al eliminar el registro del Inventario HK.'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de Inventario HK: ' . $e->getMessage(), ['exception' => $e, 'user_id' => Auth::id()]);
            return response()->json(['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'error' => $e->getMessage()], 500);
        }
    }
}