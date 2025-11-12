<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\dpb_gasconsumption;
use App\Models\dpb_sale;
use App\Models\dpb_phonecall;

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

    // GUARDAR EN LA BD Y LUEGO EN LA TABLA
    protected $sheet = [
        'gasConsumption' => 'f',
        'sale' => 'h'
    ];
    public function fillTable($sheetName, $rowData, $databaseId)
    {
        $values = [$rowData]; // La API espera un array de arrays para las filas
        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            // Ajusta este rango si las columnas para tableid están en otro lugar.
            $readRange = $sheetName . '!X:Y';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                $dataRows = array_slice($existingRows, 1);
                foreach ($dataRows as $row) {
                    $existingTableId = $row[23] ?? null; // Columna A (índice 0)
                    if ($existingTableId == $databaseId) {
                        Log::warning('Intento de registro duplicado detectado.', [
                            'table_id' => $existingTableId,
                        ]);
                        return ['message' => 'Ya existe este registro. No se permite duplicar.', 'HTTPcode' => 409];
                    }
                }
            }
            // --- FIN DE LA VERIFICACIÓN DE DUPLICADOS ---

            $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            // Manejo de la respuesta de la API y retorno de éxito/error
            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                return ['message' => 'Datos guardados con éxito.', 'HTTPcode' => 200];
            } else {
                Log::error('Fallo al añadir fila a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                return ['message' => 'Hubo un problema al guardar los datos.', 'HTTPcode' => 500];
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar datos: ' . $e->getMessage(), ['exception' => $e]);
            return ['message' => 'Error en el servidor al comunicarse con Google Sheets' . $e->getMessage(), 'HTTPcode' => 400];
        }
    }
    public function removeTable($sheetName, $databaseId)
    {
        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $readRange = $sheetName . '!X:Y';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                $dataRows = array_slice($existingRows, 1); //quitar el encabezado
                foreach ($dataRows as $index => $row) {
                    $existingTableId = $row[0] ?? null;
                    if ($existingTableId == $databaseId) {
                        $rowNumber = $index + 1;
                        break;
                    }
                }
            }
            if (empty($rowNumber)) {
                return ['message' => 'El registro no existe en la tabla.', 'HTTPcode' => 200];
            }
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
                    'startIndex' => $rowNumber,
                    'endIndex' => $rowNumber + 1
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
                return ['message' => 'Registro eliminado exitosamente.', 'HTTPcode' => 200];
            } else {
                return ['message' => 'Hubo un problema al eliminar el registro.', 'HTTPcode' => 200];
            }
        } catch (\Exception $e) {
            return ['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'HTTPcode' => 500];
        }
    }
    public function updateTable($sheetName, $databaseId, $newRowData)
    {
        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $readRange = $sheetName . '!X:Y';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                $dataRows = array_slice($existingRows, 1); //quitar el encabezado
                foreach ($dataRows as $index => $row) {
                    $existingTableId = $row[0] ?? null; // Asumiendo que el ID de la BD está en la columna Y (índice 0 de la lectura X:Y)
                    if ($existingTableId == $databaseId) {
                        // El número de fila en la hoja es el índice del array + 2 (1 por el encabezado, 1 porque los índices son base 0)
                        $rowNumber = $index + 2;
                        break;
                    }
                }
            }
            if (empty($rowNumber)) {

                return ['status' => 'fail','message' => 'El registro a actualizar no fue encontrado en la hoja de cálculo.', 'HTTPcode' => 404];
            }

            // 2. Construir el rango a actualizar. Ej: 'f!A5:X5'
            $updateRange = $sheetName . '!A' . $rowNumber . ':X' . $rowNumber;

            // 3. Preparar los datos para la actualización
            $body = new ValueRange([
                'values' => [$newRowData] // La API espera un array de filas
            ]);

            $params = [
                // USER_ENTERED permite que Google Sheets interprete fechas y números correctamente.
                'valueInputOption' => 'USER_ENTERED'
            ];

            // 4. Ejecutar la actualización
            $result = $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);

            // 5. Verificar el resultado
            if ($result->getUpdatedCells() > 0) {
                return ['status' => 'success', 'message' => 'Registro actualizado exitosamente.', 'HTTPcode' => 200];
            } else {
                Log::error('Fallo al actualizar fila en Google Sheet, no se actualizaron celdas.', ['result' => $result]);
                return ['status' => 'fail', 'message' => 'Hubo un problema al actualizar los datos en la hoja de cálculo.', 'HTTPcode' => 500];
            }

        } catch (\Exception $e) {
            Log::error('Error al actualizar datos en Google Sheet: ' . $e->getMessage(), ['exception' => $e]);
            return ['status' => 'fail', 'message' => 'Error en el servidor al comunicarse con Google Sheets: ' . $e->getMessage(), 'HTTPcode' => 500];
        }
    }




    public function getGasConsumptionFormPartial()
    {
        // Devuelve la vista Blade sin un layout completo
        return view('forms.gas_consumption_partial');
    }
    public function submitGasToSheet(Request $request)
    {
        $gas_price = 0.45;
        $rules = [
            'cala' => 'required|numeric|max:999999999|min:0',
            'lavanderia' => 'required|numeric|max:999999999|min:0',
            'cocina' => 'required|numeric|max:999999999|min:0',
            'velero' => 'required|numeric|max:999999999|min:0',
            'agua' => 'required|numeric|max:999999999|min:0',
            'date' => 'required|date|before_or_equal:today',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Falló la validación',
                'errors' => $validator->errors(),
            ], 422); // Código de estado 422 para errores de validación
        }
        $validatedData = $validator->validated(); // Obtener los datos validados

        $userId = Auth::id();
        $qry_gasconsumption_dateuser = dpb_gasconsumption::where('gasconsumption_date', $validatedData['date'])->where('gasconsumption_userid', $userId);
        $qry_gasconsumption_date = dpb_gasconsumption::where('gasconsumption_date', $validatedData['date']);

        if ($qry_gasconsumption_dateuser->count() > 0 && $qry_gasconsumption_dateuser->first()->gasconsumption_status === 1) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }
        if ($qry_gasconsumption_date->count() > 0 && $qry_gasconsumption_date->first()->gasconsumption_status === 1) {
            $validatedData['databaseId'] = $qry_gasconsumption_date->first()->gasconsumption_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 422);
        }

        $m_gasconsumption = new dpb_gasconsumption;
        $m_gasconsumption->gasconsumption_date = $validatedData['date'];
        $m_gasconsumption->gasconsumption_userid = $userId;
        $m_gasconsumption->gasconsumption_cala = $validatedData['cala'];
        $m_gasconsumption->gasconsumption_laundry = $validatedData['lavanderia'];
        $m_gasconsumption->gasconsumption_velero = $validatedData['velero'];
        $m_gasconsumption->gasconsumption_kitchen = $validatedData['cocina'];
        $m_gasconsumption->gasconsumption_hotwater = $validatedData['agua'];
        $m_gasconsumption->gasconsumption_price = $gas_price;
        $m_gasconsumption->gasconsumption_status = 1;
        $m_gasconsumption->save();

        // 2. Preparar los datos para Google Sheets
        $min = 15;
        $max = 9999;
        $rowData = [
            floatval($validatedData['cala'] * $gas_price),
            $validatedData['date'],
            mt_rand($min, $max),
            mt_rand($min, max: $max),
            $validatedData['lavanderia'],
            $validatedData['velero'],
            mt_rand($min, $max),
            $validatedData['agua'],
            mt_rand($min, $max),
            $userId,
            mt_rand($min, $max),
            floatval($validatedData['lavanderia'] * $gas_price),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['cala'],
            floatval($validatedData['cocina'] * $gas_price),
            mt_rand($min, $max),
            mt_rand($min, $max),
            floatval($validatedData['velero'] * $gas_price),
            mt_rand($min, $max),
            $validatedData['cocina'],
            floatval($validatedData['agua'] * $gas_price),
            now()->toDateTimeString(),
            $m_gasconsumption->gasconsumption_id
        ];
        $ans = $this->fillTable($this->sheet['gasConsumption'], $rowData, $m_gasconsumption->gasconsumption_id);
        return response()->json(['message' => $ans['message']], $ans['HTTPcode']);
    }
    public function showGasConsumptionRecords(Request $request)
    {
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        $year = Carbon::parse($filterDate)->year;
        $month = Carbon::parse($filterDate)->month;

        $query = dpb_gasconsumption::query();

        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('gasconsumption_date', $year)
                  ->whereMonth('gasconsumption_date', $month);
        } else {
            // Por defecto, mostrar el mes actual si no hay filtro
            $query->whereYear('gasconsumption_date', Carbon::now()->year)
                  ->whereMonth('gasconsumption_date', Carbon::now()->month);
        }

        $currentUser = Auth::user();
        $isAdmin = $currentUser->hasRole('Admin'); // Usando el método hasRole de Spatie/Laravel-Permission
        if ($isAdmin) {
            $rs_gasConsumption = $query->orderBy('gasconsumption_status', 'DESC')
                                    ->orderBy('gasconsumption_date', 'DESC')
                                    ->join('users', 'users.id', 'dpb_gasconsumptions.gasconsumption_userid')
                                    ->get();                
        }else{
            $rs_gasConsumption = $query->orderBy('gasconsumption_status', 'DESC')
                            ->orderBy('gasconsumption_date', 'DESC')
                            ->join('users', 'users.id', 'dpb_gasconsumptions.gasconsumption_userid')
                            ->where('dpb_gasconsumptions.gasconsumption_userid',$currentUser->id)
                            ->get();
        }
        try {
            $displayHeaders = [
                'gasconsumption_date' => 'Fecha',
                'name' => 'Usuario',
                'gasconsumption_cala' => 'Cala',
                'gasconsumption_laundry' => 'Lavanderia',
                'gasconsumption_kitchen' => 'Cocina',
                'gasconsumption_velero' => 'Velero',
                'gasconsumption_hotwater' => 'Agua Caliente',
                'gasconsumption_price' => 'Precio',
                'gasconsumption_status' => 'Estado'
            ];
            return view('forms.gasConsumption_records', compact('displayHeaders', 'rs_gasConsumption', 'filterDate'));
        } catch (\Exception $e) {
            Log::error('Error al cargar registros: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function updateGas(Request $request)
    {
        $gas_price = 0.45;
        $userId = Auth::id();
        $rules = [
            'cala' => 'required|numeric|max:999999999|min:0',
            'lavanderia' => 'required|numeric|max:999999999|min:0',
            'cocina' => 'required|numeric|max:999999999|min:0',
            'velero' => 'required|numeric|max:999999999|min:0',
            'agua' => 'required|numeric|max:999999999|min:0',
            'date' => 'required|date|before_or_equal:today',
            'databaseId' => 'required|integer'
        ];
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

        $m_gasconsumption = dpb_gasconsumption::find($validatedData['databaseId']);
        $m_gasconsumption->gasconsumption_date = $validatedData['date'];
        $m_gasconsumption->gasconsumption_userid = $userId;
        $m_gasconsumption->gasconsumption_cala = $validatedData['cala'];
        $m_gasconsumption->gasconsumption_laundry = $validatedData['lavanderia'];
        $m_gasconsumption->gasconsumption_velero = $validatedData['velero'];
        $m_gasconsumption->gasconsumption_kitchen = $validatedData['cocina'];
        $m_gasconsumption->gasconsumption_hotwater = $validatedData['agua'];
        $m_gasconsumption->gasconsumption_price = $gas_price;
        $m_gasconsumption->gasconsumption_status = 1;
        $m_gasconsumption->save();

        // Prepara la fila con los datos actualizados para Google Sheets
        $min = 15;
        $max = 9999;
        $newRowData = [
            floatval($validatedData['cala'] * $gas_price),
            $validatedData['date'],
            mt_rand($min, $max),
            mt_rand($min, max: $max),
            $validatedData['lavanderia'],
            $validatedData['velero'],
            mt_rand($min, $max),
            $validatedData['agua'],
            mt_rand($min, $max),
            $userId,
            mt_rand($min, $max),
            floatval($validatedData['lavanderia'] * $gas_price),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $validatedData['cala'],
            floatval($validatedData['cocina'] * $gas_price),
            mt_rand($min, $max),
            mt_rand($min, $max),
            floatval($validatedData['velero'] * $gas_price),
            mt_rand($min, $max),
            $validatedData['cocina'],
            floatval($validatedData['agua'] * $gas_price),
            now()->toDateTimeString(),
            $m_gasconsumption->gasconsumption_id
        ];

        $ans = $this->updateTable($this->sheet['gasConsumption'], $validatedData['databaseId'], $newRowData);
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function deleteGasConsumption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gasConsumption_id' => ['required', 'integer'],
        ], [
            'gasConsumption_id.required' => 'El número de fila es obligatorio para la eliminación.',
            'gasConsumption_id.integer' => 'El número de fila debe ser un número entero.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Failed', 'errors' => $validator->errors()], 422);
        }

        $gasConsumption_id = $request->input('gasConsumption_id');

        $rs_gasConsumption = dpb_gasconsumption::find($gasConsumption_id);
        $rs_gasConsumption->gasConsumption_status = 0;
        $rs_gasConsumption->save();

        $result = $this->removeTable($this->sheet['gasConsumption'], $gasConsumption_id);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }


    public function showSalesForm()
    {
        return view('forms.sales_partial');
    }
    public function submitSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'montoWeb' => ['required', 'numeric', 'min:0'],
            'montoCallcenter' => ['required', 'numeric', 'min:0'],
            'montoOTA' => ['required', 'numeric', 'min:0'],
            'montoCorporativo' => ['required', 'numeric', 'min:0'],
            'montoAgenciaNac' => ['required', 'numeric', 'min:0'],
            'montoAgenciaInt' => ['required', 'numeric', 'min:0'],
            'montoArenas' => ['required', 'numeric', 'min:0'],
            'fechaRegistro' => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
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
                'status' => 'fail',
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $userId = Auth::id(); // ID del usuario autenticado
        $validatedData = $validator->validated(); // Obtener los datos validados

        $qry_date = dpb_sale::where('sale_date', $validatedData['fechaRegistro']);
        $qry_dateuser = dpb_sale::where('sale_date', $validatedData['fechaRegistro'])->where('sale_userid', $userId);

        if ($qry_dateuser->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);
        }
        if ($qry_date->count() > 0) {
            $validatedData['databaseId'] = $qry_date->first()->sale_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 422);
        }

        $m_sale = new dpb_sale;
        $m_sale->sale_date = $validatedData['fechaRegistro'];
        $m_sale->sale_userid = $userId;
        $m_sale->sale_corporative = $validatedData['montoCorporativo'];
        $m_sale->sale_national = $validatedData['montoAgenciaNac'];
        $m_sale->sale_international = $validatedData['montoAgenciaInt'];
        $m_sale->sale_callcenter = $validatedData['montoCallcenter'];
        $m_sale->sale_ota = $validatedData['montoOTA'];
        $m_sale->sale_arenas = $validatedData['montoArenas'];
        $m_sale->sale_web = $validatedData['montoWeb'];
        $m_sale->sale_status = 1;
        $m_sale->save();

        $min = 15;
        $max = 9999;
        $rowData = [
            mt_rand($min, $max),
            floatval($request->input('montoCorporativo')),
            mt_rand($min, $max),
            floatval($request->input('montoAgenciaNac')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $userId,
            mt_rand($min, $max),
            floatval($request->input('montoCallcenter')),
            mt_rand($min, $max),
            floatval($request->input('montoOTA')),
            mt_rand($min, $max),
            floatval($request->input('montoAgenciaInt')),
            floatval($request->input('montoArenas')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            now()->toDateTimeString(),
            mt_rand($min, $max),
            mt_rand($min, $max),
            floatval($request->input('montoWeb')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('fechaRegistro'),
            $m_sale->sale_id
        ];
        $ans = $this->fillTable($this->sheet['sale'], $rowData, $m_sale->sale_id);
        return response()->json(['message' => $ans['message']], $ans['HTTPcode']);
    }
    public function updateSales(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'montoWeb' => ['required', 'numeric', 'min:0'],
            'montoCallcenter' => ['required', 'numeric', 'min:0'],
            'montoOTA' => ['required', 'numeric', 'min:0'],
            'montoCorporativo' => ['required', 'numeric', 'min:0'],
            'montoAgenciaNac' => ['required', 'numeric', 'min:0'],
            'montoAgenciaInt' => ['required', 'numeric', 'min:0'],
            'montoArenas' => ['required', 'numeric', 'min:0'],
            'fechaRegistro' => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
            'databaseId' => ['required', 'numeric', 'min:1'],
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
            'databaseId.required' => 'El campo ID es obligatorio.',
            'databaseId.numeric' => 'El campo ID debe ser un número.',
            'databaseId.min' => 'El campo ID no puede ser negativo.',

        ]);

        if ($validator->fails()) {
            // Si la validación falla, devuelve un JSON con los errores
            return response()->json([
                'success' => false,
                'message' => 'Falló la validación',
                'errors' => $validator->errors(),
            ], 422); // Código de estado 422 para errores de validación
        }
        $validatedData = $validator->validated(); // Obtener los datos validados

        $m_sale = dpb_sale::find($validatedData['databaseId']);
        $m_sale->sale_date = $validatedData['fechaRegistro'];
        $m_sale->sale_userid = $userId;
        $m_sale->sale_corporative = $validatedData['montoCorporativo'];
        $m_sale->sale_national = $validatedData['montoAgenciaNac'];
        $m_sale->sale_international = $validatedData['montoAgenciaInt'];
        $m_sale->sale_callcenter = $validatedData['montoCallcenter'];
        $m_sale->sale_ota = $validatedData['montoOTA'];
        $m_sale->sale_arenas = $validatedData['montoArenas'];
        $m_sale->sale_web = $validatedData['montoWeb'];
        $m_sale->sale_status = 1;
        $m_sale->save();

        // Prepara la fila con los datos actualizados para Google Sheets
        $min = 15;
        $max = 9999;
        $newRowData = [
            mt_rand($min, $max),
            floatval($request->input('montoCorporativo')),
            mt_rand($min, $max),
            floatval($request->input('montoAgenciaNac')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $userId,
            mt_rand($min, $max),
            floatval($request->input('montoCallcenter')),
            mt_rand($min, $max),
            floatval($request->input('montoOTA')),
            mt_rand($min, $max),
            floatval($request->input('montoAgenciaInt')),
            floatval($request->input('montoArenas')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            now()->toDateTimeString(),
            mt_rand($min, $max),
            mt_rand($min, $max),
            floatval($request->input('montoWeb')),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('fechaRegistro'),
            $m_sale->sale_id
        ];

        $ans = $this->updateTable($this->sheet['sale'], $validatedData['databaseId'], $newRowData);
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }

    public function showSalesRecords(Request $request)
    {
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        $year = Carbon::parse($filterDate)->year;
        $month = Carbon::parse($filterDate)->month;

        $query = dpb_sale::query();

        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('sale_date', $year)
                  ->whereMonth('sale_date', $month);
        } else {
            $query->whereYear('sale_date', Carbon::now()->year)
                  ->whereMonth('sale_date', Carbon::now()->month);
        }

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin'); // Usando el método hasRole de Spatie/Laravel-Permission

        try {
            if ($isAdmin) {
                $records = dpb_sale::ORDERBY('sale_status', 'DESC')->ORDERBY('sale_date', 'DESC')
                    ->JOIN('users', 'users.id', 'dpb_sales.sale_userid')->GET();
            } else {
                $records = dpb_sale::ORDERBY('sale_status', 'DESC')->ORDERBY('sale_date', 'DESC')
                ->where('dpb_sales.sale_userid',$currentUserId)
                ->JOIN('users', 'users.id', 'dpb_sales.sale_userid')->GET();
            }
            
            $displayHeaders = [
                'sale_date' => 'Fecha',
                'name' => 'Usuario',
                'sale_corporative' => 'Corporativo',
                'sale_national' => 'Ag. Nacional',
                'sale_international' => 'Ag. Internacional',
                'sale_callcenter' => 'Callcenter',
                'sale_ota' => 'OTAs',
                'sale_arenas' => 'Arenas',
                'sale_web' => 'Pag. Web',
                'sale_status' => 'Estado'
            ];
            return view('forms.sales_records', compact('displayHeaders', 'records'));
        } catch (\Exception $e) {
            Log::error('Error al cargar registros: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'integer', 'min:1'],
        ], [
            'sale_id.required' => 'El número de fila es obligatorio para la eliminación.',
            'sale_id.integer' => 'El número de fila debe ser un número entero.',
            'sale_id.min' => 'No se puede eliminar la fila de encabezados.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $sale_id = $request->input('sale_id');

        $rs_sale = dpb_sale::find($sale_id);
        $rs_sale->sale_status = 0;
        $rs_sale->save();

        $rowNumber = $request->input('sale_id');
        $result = $this->removeTable($this->sheet['sale'], $sale_id);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }


    // protected $phoneCallHeadersMap = [
    //     3 => 'Usuario',
    //     4 => 'Fecha',
    //     6 => 'Cantidad',
    //     8 => 'Ventas Logradas',
    //     11 => 'Tiempo Prom',
    //     13 => 'Creación',
    // ];
    public function showPhoneCallForm()
    {
        return view('forms.phonecall_partial');
    }
    public function submitPhoneCall(Request $request)
    {
        // 1. Validación de los datos
        $validator = Validator::make($request->all(), [
            'fechaRegistro' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
            'cantidadLlamadas' => ['required', 'integer', 'min:0'],
            'ventasLogradas' => ['required', 'integer', 'min:0'],
            'tiempoPromedioLlamadas' => ['required', 'numeric', 'min:0'],
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

        $userId = Auth::id();
        $qry_phonecall_dateuser = dpb_phonecall::where('phonecall_date', $validatedData['date'])->where('phonecall_userid', $userId);
        $qry_phonecall_date = dpb_phonecall::where('phonecall_date', $validatedData['date']);

        if ($qry_phonecall_dateuser->count() > 0 && $qry_phonecall_dateuser->first()->phonecall_status === 1) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }
        if ($qry_phonecall_date->count() > 0 && $qry_phonecall_date->first()->phonecall_status === 1) {
            $validatedData['databaseId'] = $qry_phonecall_date->first()->phonecall_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 204);
        }

        $m_gasconsumption = new dpb_gasconsumption;
        $m_gasconsumption->gasconsumption_date = $validatedData['date'];
        $m_gasconsumption->gasconsumption_userid = $userId;
        $m_gasconsumption->gasconsumption_cala = $validatedData['cala'];
        $m_gasconsumption->gasconsumption_laundry = $validatedData['lavanderia'];
        $m_gasconsumption->gasconsumption_velero = $validatedData['velero'];
        $m_gasconsumption->gasconsumption_kitchen = $validatedData['cocina'];
        $m_gasconsumption->gasconsumption_hotwater = $validatedData['agua'];
        $m_gasconsumption->gasconsumption_price = $gas_price;
        $m_gasconsumption->gasconsumption_status = 1;
        $m_gasconsumption->save();



        $min = 15; // Valores de ejemplo para columnas de relleno
        $max = 9999;
        $timestampInsercion = Carbon::now()->toDateTimeString(); // Timestamp de la inserción
        // Construcción de la fila de datos con 24 columnas
        $rowData = [
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max), 
            $userId,            
            $request->input('fechaRegistro'),  
            mt_rand($min, $max),
            floatval($request->input('cantidadLlamadas')),
            mt_rand($min, $max), 
            floatval($request->input('ventasLogradas')), 
            mt_rand($min, $max), 
            mt_rand($min, $max), 
            floatval($request->input('tiempoPromedioLlamadas')),
            mt_rand($min, $max), 
            $timestampInsercion, 
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
        ];

        $ans = $this->fillTable('j', $rowData, $request->input('fechaRegistro'), $userId);

        return response()->json([
            'message' => $ans['message']
        ], $ans['HTTPcode']);
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
                $filteredRows = array_reverse($filteredRows);
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
        $validator = Validator::make($request->all(), [
            'totalGasto' => ['required', 'numeric', 'min:0'],
            'cantidadCiclos' => ['required', 'integer', 'min:0'],
            'fechaInicio' => ['required', 'date'], // NUEVA REGLA
            'fechaFin' => ['required', 'date', 'after_or_equal:fechaInicio', 'before_or_equal:today'],
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
            $range = 'g!A2';

            $body = new ValueRange([
                'values' => $values
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                return response()->json(['message' => 'Información enviada correctamente'], 200); // Código 200 para éxito
            } else {
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
        $sheetName = 'g';
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

            // Usa el mapeo de encabezados específico para lavandería
            $customHeadersMap = $this->laundryHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
                foreach ($values as $index => $row) {
                    $rowUserId = $row[4] ?? null; // Asume user_id está en la primera columna (índice 0)

                    if ($isAdmin || (string) $rowUserId === (string) $currentUserId) {
                        $recordData = ['row_number_gs' => ($index + 2)];
                        $recordData['data_cols'] = [];
                        foreach ($displayHeaders as $colIndex => $headerName) {
                            $recordData['data_cols'][$colIndex] = $row[$colIndex] ?? '';
                        }
                        $filteredRows[] = $recordData;
                    }
                }

                $filteredRows = array_reverse($filteredRows);
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
        $result = $this->removeTable('g', $rowNumber);
        return response()->json([
            'message' => $result['message'],
        ], $result['HTTPcode']);
    }


    protected $auditorHeadersMap = [
        4 => 'Fecha',
        0 => 'AB',
        1 => 'Otro',
        2 => 'Usuario',
        3 => 'Creacion',
    ];
    public function showAuditorForm()
    {
        return view('forms.auditor_partial');
    }
    public function submitAuditor(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'montoAB' => ['required', 'numeric', 'min:0'],
            'montoOtro' => ['required', 'numeric', 'min:0'],
            'fechaRegistro' => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
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
        $ans = $this->fillTable('l', $rowData, $request->input('fechaRegistro'), $userId);
        return response()->json(['message' => $ans['message']], $ans['HTTPcode']);
    }
    public function showAuditorRecords()
    {
        $sheetName = 'l';
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
            $customHeadersMap = $this->auditorHeadersMap;

            foreach ($customHeadersMap as $colIndex => $customName) {
                $displayHeaders[$colIndex] = $customName;
            }

            if (!empty($values)) {
                array_shift($values); // Remueve la fila de encabezados
                $filteredRows = [];
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
                $filteredRows = array_reverse($filteredRows);
                $records = array_slice($filteredRows, -$limitRows);
            }

            return view('forms.auditor_records', compact('displayHeaders', 'records'));

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
        $result = $this->removeTable('l', $rowNumber);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }


    public function showInventoryHkForm()
    {
        return view('forms.inventoryhk_partial');
    }
    public function submitInventoryHk(Request $request)
    {
        // 1. Validación de los datos
        $validator = Validator::make($request->all(), [
            'fechaInventario' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
            'sheet_k' => ['nullable', 'integer', 'min:0'],
            'sheet_q' => ['nullable', 'integer', 'min:0'],
            'pillowcase_k' => ['nullable', 'integer', 'min:0'],
            'pillowcase_q' => ['nullable', 'integer', 'min:0'],
            'pillow_k' => ['nullable', 'integer', 'min:0'],
            'pillow_q' => ['nullable', 'integer', 'min:0'],
            'mattressprotector_k' => ['nullable', 'integer', 'min:0'],
            'mattressprotector_q' => ['nullable', 'integer', 'min:0'],
            'towel_blank' => ['nullable', 'integer', 'min:0'],
            'hand_towel' => ['nullable', 'integer', 'min:0'],
            'foot_towel' => ['nullable', 'integer', 'min:0'],
            'face_towel' => ['nullable', 'integer', 'min:0'],
            'towel_blue' => ['nullable', 'integer', 'min:0'],
            'blanket_blue' => ['nullable', 'integer', 'min:0'],
            'blanket_green' => ['nullable', 'integer', 'min:0'],
            'duveth_k' => ['nullable', 'integer', 'min:0'],
            'duveth_q' => ['nullable', 'integer', 'min:0'],
            'cover_k' => ['nullable', 'integer', 'min:0'],
            'cover_q' => ['nullable', 'integer', 'min:0'],
            'bedskirt_k' => ['nullable', 'integer', 'min:0'],
            'bedskirt_q' => ['nullable', 'integer', 'min:0'],
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
                foreach ($values as $index => $row) {
                    $rowUserId = $row[0] ?? null; // Obtener el ID de usuario de la fila
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