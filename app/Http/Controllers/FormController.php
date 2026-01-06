<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\dpb_gasconsumption;
use App\Models\dpb_sale;
use App\Models\dpb_phonecall;
use App\Models\dpb_laundry;
use App\Models\dpb_inventoryhk;
use App\Models\dpb_income;
use App\Models\dpb_gasprice;
use App\Models\User;

// Importaciones para Google Sheets API
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetRequest;
use Google\Service\Sheets\DeleteDimensionRequest;

class FormController extends Controller
{
    protected $sheet = [
        'gasConsumption' => 'f',
        'sale' => 'h',
        'phonecall' => 'j',
        'laundry' => 'g',
        'inventoryhk'=> 'k',
        'newSale' => 'l'
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
                    $existingTableId = $row[0] ?? null; // Columna X (índice 0)
                    if ($existingTableId == $databaseId) {
                        $ans = $this->updateTable($sheetName, $databaseId, $RowData);
                        Log::warning('Intento de registro duplicado detectado.', ['table_id' => $existingTableId,]);
                        if ($ans['status'] === 'success') {
                            return ['status' => 'fail', 'message' => 'Ya existe este registro. Se actualizó.', 'HTTPcode' => 409];
                        } else {
                            return ['status' => 'fail', 'message' => 'Ya existe este registro. No se permite duplicar.', 'HTTPcode' => 409];
                        }
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
                return ['status' => 'success','message' => 'Datos guardados con éxito.', 'HTTPcode' => 200];
            } else {
                Log::error('Fallo al añadir fila a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                return ['status' => 'fail','message' => 'Hubo un problema al guardar los datos.', 'HTTPcode' => 500];
            }

        } catch (\Exception $e) {
            Log::error('Error al guardar datos: ' . $e->getMessage(), ['exception' => $e]);
            return ['status' => 'fail', 'message' => 'Error en el servidor al comunicarse con Google Sheets' . $e->getMessage(), 'HTTPcode' => 400];
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
                return ['message' => 'Hubo un problema al eliminar el registro.', 'HTTPcode' => 500];
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

    public function fillTableSale($sale_date)
    {
        $sheetName = $this->sheet['sale'];
        $rs_sale = dpb_sale::WHERE('sale_date', $sale_date)->WHERE('sale_status',1)->first();
        $rs_income = dpb_income::WHERE('income_date', $sale_date)->WHERE('income_status',1)->first();
        $userId = 3;
        if(!empty($rs_sale['sale_userid'])) {
            $userId = $rs_sale['sale_userid'];
         }else{
            if(!empty($rs_sale['income_userid'])) {
                $userId = $rs_sale['income_userid'];
            }else{    
                $userId = Auth::id();
            }
         } 

        $min = 15;
        $max = 9999;
        $date = $sale_date ? Carbon::parse($sale_date) : null;
        $rowData = [
            mt_rand($min, $max),
            !empty($rs_sale->sale_corporative)  ? $rs_sale->sale_corporative : 0,
            mt_rand($min, $max),
            !empty($rs_sale->sale_national)     ? $rs_sale->sale_national : 0,
            mt_rand($min, $max),
            !empty($rs_income['income_other'])  ? $rs_income['income_other'] : 0,
            $userId,
            !empty($rs_income['income_ab'])     ? $rs_income['income_ab'] : 0,
            !empty($rs_sale->sale_callcenter)   ? $rs_sale->sale_callcenter : 0,
            mt_rand($min, $max),
            !empty($rs_sale->sale_ota)          ? $rs_sale->sale_ota : 0,
            mt_rand($min, $max),
            !empty($rs_sale->sale_international) ? $rs_sale->sale_international : 0,
            !empty($rs_sale->sale_arenas)       ? $rs_sale->sale_arenas : 0,
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_sale->created_at)        ? $rs_sale->created_at : 0,
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_sale->sale_web)          ? $rs_sale->sale_web : 0,
            mt_rand($min, $max),
            $date->format('Y-m-d'),
            !empty($rs_income['income_id'])     ? $rs_income['income_id'] : 0,
            !empty($rs_sale->sale_id)           ? $rs_sale->sale_id : 0
        ];

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $readRange = $sheetName . '!V:W';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                $dataRows = array_slice($existingRows, 1); //quitar el encabezado
                foreach ($dataRows as $index => $row) {
                    $existingTableId = $row[0] ?? null; // Asumiendo que el ID de la BD está en la columna Y (índice 0 de la lectura X:Y)
                    if ($existingTableId == $sale_date) {
                        // El número de fila en la hoja es el índice del array + 2 (1 por el encabezado, 1 porque los índices son base 0)
                        $rowNumber = $index + 2;
                        break;
                    }
                }
            }
            if (empty($rowNumber)) { //INSERTAR LA FILA NUEVA
                $range = $sheetName . '!A2'; // Se añadirán datos a partir de la celda A2 en la hoja

                $body = new ValueRange([
                    'values' => [$rowData]
                ]);

                $params = [
                    'valueInputOption' => 'RAW'
                ];

                $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
                // Manejo de la respuesta de la API y retorno de éxito/error
                if ($result->getUpdates() && $result->getUpdates()->getUpdatedRows() > 0) {
                    return ['status' => 'success','message' => 'Datos guardados con éxito.', 'HTTPcode' => 200];
                } else {
                    Log::error('Fallo al añadir fila a Google Sheet, no se actualizaron filas.', ['result' => $result]);
                    return ['status' => 'fail','message' => 'Hubo un problema al guardar los datos.', 'HTTPcode' => 500];
                }
            }

            // 2. Construir el rango a actualizar. Ej: 'f!A5:X5'
            $updateRange = $sheetName . '!A' . $rowNumber . ':X' . $rowNumber;

            // 3. Preparar los datos para la actualización
            $body = new ValueRange([
                'values' => [$rowData] // La API espera un array de filas
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
    public function removeTableSale($sale_date)
    {
        $sheetName = $this->sheet['sale'];
        $qry_sale = dpb_sale::WHERE('sale_date', $sale_date)->WHERE('sale_status',1);
        $qry_income = dpb_income::WHERE('income_date', $sale_date)->WHERE('income_status',1);

        if ($qry_sale->count() > 0 || $qry_income->count() > 0) {
            $ans = $this->fillTableSale($sale_date);
            return $ans;
        }

        try {
            $client = new Client();
            $client->setAuthConfig(config('google.service_account_credentials_path'));
            $client->addScope(Sheets::SPREADSHEETS);
            $service = new Sheets($client);
            $spreadsheetId = config('google.sheet_id');

            $readRange = $sheetName . '!V:W';
            $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
            $existingRows = $response->getValues();

            if ($existingRows) {
                $dataRows = array_slice($existingRows, 1); //quitar el encabezado
                foreach ($dataRows as $index => $row) {
                    $existingTableId = $row[0] ?? null; // Asumiendo que el ID de la BD está en la columna Y (índice 0 de la lectura X:Y)
                    if ($existingTableId == $sale_date) {
                        // El número de fila en la hoja es el índice del array + 2 (1 por el encabezado, 1 porque los índices son base 0)
                        $rowNumber = $index + 2;
                        break;
                    }
                }
            }
            if (empty($rowNumber)) {
                return ['status' => 'fail', 'message' => 'El registro no existe en la tabla.', 'HTTPcode' => 500];
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
                    'startIndex' => $rowNumber-1,
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
                return ['status' => 'success', 'message' => 'Registro eliminado exitosamente.', 'HTTPcode' => 200];
            } else {
                return ['status' => 'fail', 'message' => 'Hubo un problema al eliminar el registro.', 'HTTPcode' => 500];
            }
        } catch (\Exception $e) {
            return ['message' => 'Error en el servidor al comunicarse con Google Sheets.', 'HTTPcode' => 500];
        }
    }
    public function removeCheckedNewSale($rowNumber){
        //ELIMINAR LA FILA
        $targetSheetId = null;
        $client = new Client();
        $client->setAuthConfig(config('google.service_account_credentials_path'));
        $client->addScope(Sheets::SPREADSHEETS);
        $service = new Sheets($client);
        $spreadsheetId = config('google.sheet_id');
        $sheetName = $this->sheet['newSale'];


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
                'startIndex' => $rowNumber-1,
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
            return ['message' => 'Registro eliminado exitosamente.', 'HTTPcode' => 200];
        } else {
            return ['message' => 'Hubo un problema al eliminar el registro.', 'HTTPcode' => 500];
        }

    }
    public function checkNewSale(){
        $client = new Client();
        $client->setAuthConfig(config('google.service_account_credentials_path'));
        $client->addScope(Sheets::SPREADSHEETS);
        $service = new Sheets($client);
        $spreadsheetId = config('google.sheet_id');
        $sheetName = $this->sheet['newSale'];

        // 1. Cambiamos el rango para leer desde la columna A hasta la Y
        $readRange = $sheetName . '!A:X'; 
        $response = $service->spreadsheets_values->get($spreadsheetId, $readRange);
        $existingRows = $response->getValues();

        $newRowData = null; // Variable para guardar toda la línea
        if ($existingRows) {
            $dataRows = array_slice($existingRows, 1); // Quitar encabezado
            
            foreach ($dataRows as $index => $row) {
                $existingTableId = $row[23] ?? null; 

                if ($existingTableId == 0) {
                    $rowNumber = $index + 2;
                    $newRowData = $row; // 3. Guardamos TODO el contenido de la fila encontrada
                    break;
                }
            }
        }

        if (empty($newRowData)) { 
            return [
                'status' => 'success',
                'message' => 'No hay registros nuevos.', 
                'HTTPcode' => 200
            ];
        } else {
            $dataForRequest = [
                'fechaRegistro'    => $newRowData[21],
                'montoWeb'         => $newRowData[19],
                'montoCallcenter'  => $newRowData[8],
                'montoOTA'         => $newRowData[10],
                'montoCorporativo' => $newRowData[1],
                'montoAgenciaNac'  => $newRowData[3],
                'montoAgenciaInt'  => $newRowData[12],
                'montoArenas'      => $newRowData[13],
                'userId'           => 3,
            ];

            // 2. Creamos un objeto Request manualmente
            $fakeRequest = new \Illuminate\Http\Request();
            $fakeRequest->replace($dataForRequest); // Inyectamos los datos

            // 3. Llamamos a la función submitSales
            $salesResponse = $this->submitSales($fakeRequest);

            // 4. Procesamos la respuesta (como submitSales devuelve un JsonResponse, obtenemos el contenido)
            $result = json_decode($salesResponse->getContent(), true);

            switch ($result['status']) {
                case 'success':
                    $removeResponse = $this->removeCheckedNewSale($rowNumber);
                    // Aquí podrías actualizar la columna X en Google Sheets para poner el ID de la BD
                    // y que no se vuelva a procesar.
                    return [
                        'status' => 'success',
                        'message' => 'Procesado desde Sheets: ' . $result['message'],
                        'HTTPcode' => 200
                    ];

                    break;
                case 'confirm':
                    $dataForRequest['databaseId'] = $result['data']['databaseId'];
                    $fakeRequest->replace($dataForRequest); // Inyectamos los datos
                    $updResponse = $this->updateSales($fakeRequest);
                    $removeResponse = $this->removeCheckedNewSale($rowNumber);
                    return [
                        'status' => 'success',
                        'message' => 'El registro fue actualizado.',
                        'HTTPcode' => 200
                    ];
                    break;
                default:
                    $updateRange = $sheetName . '!A' . $rowNumber . ':X' . $rowNumber;
                    $newRowData[23] = 1;
                    $body = new ValueRange([
                        'values' => [$newRowData] // La API espera un array de filas
                    ]);
                    $params = [
                        'valueInputOption' => 'USER_ENTERED'
                    ];
                    $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);

                    return [
                        'status' => 'fail',
                        'message' => 'Error al procesar fila: ' . ($result['message'] ?? 'Error desconocido'),
                        'errors' => $result['errors'] ?? null,
                        'HTTPcode' => $salesResponse->getStatusCode()
                    ];
                    break;
            }
        }
    }

    public function getGasConsumptionFormPartial()
    {
        // Devuelve la vista Blade sin un layout completo
        return view('forms.gas_consumption_partial');
    }
    public function submitGasToSheet(Request $request)
    {
        $rs_gasprice = dpb_gasprice::WHERE('gasprice_status',1)->ORDERBY('gasprice_id','DESC')->first();
        $gas_price = (!empty($rs_gasprice['gasprice_price'])) ? $rs_gasprice['gasprice_price'] : 1;
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
        // 1. DETERMINACIÓN DEL PERÍODO Y FECHAS LÍMITE
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        
        $carbonDate = Carbon::parse($filterDate);
        $year = $carbonDate->year;
        $month = $carbonDate->month;

        // Fechas de inicio, fin de mes y fecha actual
        $startDate = $carbonDate->firstOfMonth()->format('Y-m-d');
        $endDate = $carbonDate->lastOfMonth()->format('Y-m-d');
        $hoy = Carbon::now()->format('Y-m-d'); // 👈 Fecha actual para limitar la búsqueda

        // 2. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (Mantiene tu lógica existente)
        $query = dpb_gasconsumption::query();
        $query->whereYear('gasconsumption_date', $year)
              ->whereMonth('gasconsumption_date', $month);

        $currentUser = Auth::user();
        $isAdmin = $currentUser->hasRole('Admin');
        
        // Aplica filtro de usuario si no es Admin
        if (!$isAdmin) {
            $query->where('dpb_gasconsumptions.gasconsumption_userid', $currentUser->id);
        }

        $query->orderBy('gasconsumption_status', 'DESC')
              ->orderBy('gasconsumption_date', 'DESC')
              ->join('users', 'users.id', 'dpb_gasconsumptions.gasconsumption_userid');

        $rs_gasConsumption = $query->get();

        // 3. VERIFICACIÓN DE DÍAS FALTANTES (BRECHAS)

        // Condición de filtro de usuario para el LEFT JOIN
        $userIdCondition = $isAdmin ? "1=1" : "registros.gasconsumption_userid = " . $currentUser->id;

        $dias_faltantes = DB::select("
            SELECT
                DATE_FORMAT(dias_del_mes.fecha_completa, '%d/%m') AS dia_sin_datos
            FROM
                (
                    -- Genera secuencia de fechas (corregido para evitar duplicados)
                    SELECT DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY AS fecha_completa
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
                          UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                         (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) b
                    WHERE 
                        DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY <= '$endDate'
                        -- 🚨 CORRECCIÓN 1: Excluir fechas futuras
                        AND DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY < '$hoy'
                ) AS dias_del_mes
            LEFT JOIN
                dpb_gasconsumptions AS registros
            ON
                dias_del_mes.fecha_completa = DATE(registros.gasconsumption_date)
                -- 🚨 CORRECCIÓN 2: Mover el filtro de usuario al ON para la correcta identificación de brechas
                AND $userIdCondition
            WHERE
                registros.gasconsumption_date IS NULL
        ");

        $alerta_mensaje = null;

        if (count($dias_faltantes) > 0) {
            $dias_sin_datos = array_map(function($d){
                return $d->dia_sin_datos;
            }, $dias_faltantes);

            // Ahora el array $dias_sin_datos solo contendrá fechas únicas (si la consulta está bien)
            $dias_str = implode(', ', $dias_sin_datos);
            $alerta_mensaje = "Advertencia: Faltan datos para los siguientes días del mes: " . $dias_str;
        }

        // 4. RENDERIZAR VISTA
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
            // Pasar el mensaje de alerta a la vista
            return view('forms.gasConsumption_records', compact('displayHeaders', 'rs_gasConsumption', 'filterDate', 'alerta_mensaje'));
            
        } catch (\Exception $e) {
            Log::error('Error al cargar registros: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function updateGas(Request $request)
    {
        $rs_gasprice = dpb_gasprice::WHERE('gasprice_status',1)->first();
        $gas_price = (!empty($rs_gasprice['gasprice_price'])) ? $rs_gasprice['gasprice_price'] : 0;

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
        $userId = (!empty($request->input('userId'))) ? $request->input('userId') : Auth::id(); // ID del usuario autenticado
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

        $ans = $this->fillTableSale($validatedData['fechaRegistro']);
        return response()->json(['status' => $ans['status'], 'message' => $ans['message']], $ans['HTTPcode']);
    }
    public function updateSales(Request $request)
    {
        $userId = (!empty($request->input('userId'))) ? $request->input('userId') : Auth::id(); // ID del usuario autenticado
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

        $ans = $this->fillTableSale($validatedData['fechaRegistro']);

        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function showSalesRecords(Request $request)
    {
        // 1. DETERMINACIÓN DEL PERÍODO Y FECHAS LÍMITE
        // 🚨 Fecha por defecto es AYER (subDay()), a menos que se filtre
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        
        $carbonDate = Carbon::parse($filterDate);
        $year = $carbonDate->year;
        $month = $carbonDate->month;

        // Definir las fechas límite para las consultas
        $startDate = $carbonDate->firstOfMonth()->format('Y-m-d');
        $endDate = $carbonDate->lastOfMonth()->format('Y-m-d');
        $hoy = Carbon::now()->format('Y-m-d'); // Fecha actual para limitar la búsqueda

        // 2. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL
        $query = dpb_sale::query();

        // Aplicar filtro por mes/año
        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('sale_date', $year)->whereMonth('sale_date', $month);
        } else {
            $query->whereYear('sale_date', Carbon::now()->year)->whereMonth('sale_date', Carbon::now()->month);
        }
        
        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin');

        // 3. VERIFICACIÓN DE BRECHAS DE FECHAS (Faltan registros de VENTA)
        $userIdConditionBrecha = $isAdmin ? "1=1" : "registros.sale_userid = " . $currentUserId;

        $dias_faltantes = DB::select("
            SELECT
                DATE_FORMAT(dias_del_mes.fecha_completa, '%d/%m') AS dia_sin_datos
            FROM
                (
                    -- Generar secuencia de fechas
                    SELECT DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY AS fecha_completa
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
                          UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                         (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) b
                    WHERE 
                        DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY <= '$endDate'
                        AND DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY < '$hoy'
                ) AS dias_del_mes
            LEFT JOIN
                dpb_sales AS registros
            ON
                dias_del_mes.fecha_completa = DATE(registros.sale_date)
                AND registros.sale_status = 1 
                AND $userIdConditionBrecha 
            WHERE
                registros.sale_date IS NULL
        ");

        $alerta_brechas = null;
        if (count($dias_faltantes) > 0) {
            $dias_str = implode(', ', array_map(fn($d) => $d->dia_sin_datos, $dias_faltantes));
            $alerta_brechas = "Faltan registros de ventas ACTIVAS para los días: " . $dias_str;
        }

        // 4. VERIFICACIÓN DE EQUIVALENCIA (Ventas vs Ingresos) 🚨 NUEVO CÓDIGO 🚨
        
        // La condición de usuario para la consulta SQL de equivalencia
        $userIdConditionEq = $isAdmin ? "" : " AND T1.sale_userid = " . $currentUserId;
        
        $registros_sin_equivalencia = DB::select("
            SELECT
                DATE_FORMAT(T1.sale_date, '%d/%m') AS fecha_sin_equivalencia
            FROM
                dpb_sales AS T1 -- Tabla de Ventas (fuente)
            LEFT JOIN
                dpb_incomes AS T2 -- Tabla de Ingresos (destino/equivalente)
            ON
                DATE(T1.sale_date) = DATE(T2.income_date)
                -- Solo consideramos equivalencia si el registro de ingresos también está activo
                AND T2.income_status = 1 
            WHERE
                -- T2 es NULL: No hay registro de ingreso ACTIVO que coincida
                T2.income_date IS NULL
                -- Solo revisamos registros de ventas activos
                AND T1.sale_status = 1
                -- Aplicar filtro de mes/año
                AND YEAR(T1.sale_date) = $year
                AND MONTH(T1.sale_date) = $month
                -- Aplicar filtro de usuario
                $userIdConditionEq
            GROUP BY
                fecha_sin_equivalencia
        ");

        $alerta_equivalencia = null;
        if (count($registros_sin_equivalencia) > 0) {
            $fechas_str = implode(', ', array_map(fn($r) => $r->fecha_sin_equivalencia, $registros_sin_equivalencia));
            $alerta_equivalencia = "Registros de ventas activos sin equivalente de ingresos activo en las fechas: " . $fechas_str;
        }
        
        // 5. COMBINAR MENSAJES DE ALERTA
        $alerta_final = null;
        if ($alerta_brechas && $alerta_equivalencia) {
            $alerta_final = $alerta_brechas . " || " . $alerta_equivalencia;
        } elseif ($alerta_brechas) {
            $alerta_final = $alerta_brechas;
        } elseif ($alerta_equivalencia) {
            $alerta_final = $alerta_equivalencia;
        }


        // 6. OBTENER REGISTROS FINALES Y RENDERIZAR VISTA
        try {
            // El resto de la lógica de obtención de registros se mantiene igual
            if ($isAdmin) {
                $records = $query->ORDERBY('sale_status', 'DESC')->ORDERBY('sale_date', 'DESC')
                    ->JOIN('users', 'users.id', 'dpb_sales.sale_userid')->GET();
            } else {
                $records = $query->ORDERBY('sale_status', 'DESC')->ORDERBY('sale_date', 'DESC')
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

            // 7. Pasar el mensaje de alerta final a la vista
            return view('forms.sales_records', compact('displayHeaders', 'records', 'filterDate', 'alerta_final'));

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
            'sale_id.min' => 'No se puede eliminar la fila 0.',
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

        $result = $this->removeTableSale(date('Y-m-d', strtotime($rs_sale['sale_date'])));
        return response()->json(['status' => $result['status'], 'message' => $result['message']], $result['HTTPcode']);
    }


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

        $validatedData = $validator->validated();
        $userId = Auth::id();

        $qry_phonecall_dateuser = dpb_phonecall::where('phonecall_date', $validatedData['fechaRegistro'])->where('phonecall_userid', $userId)->where('phonecall_status',1);
        if ($qry_phonecall_dateuser->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }

        $qry_phonecall_date = dpb_phonecall::where('phonecall_date', $validatedData['fechaRegistro'])->where('phonecall_status',1);
        if ($qry_phonecall_date->count() > 0) {
            $validatedData['databaseId'] = $qry_phonecall_date->first()->phonecall_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }

        $m_phonecall = new dpb_phonecall;
        $m_phonecall->phonecall_date = $validatedData['fechaRegistro'];
        $m_phonecall->phonecall_userid = $userId;
        $m_phonecall->phonecall_quantity = $validatedData['cantidadLlamadas'];
        $m_phonecall->phonecall_success = $validatedData['ventasLogradas'];
        $m_phonecall->phonecall_average = $validatedData['tiempoPromedioLlamadas'];
        $m_phonecall->phonecall_status = 1;
        $m_phonecall->save();

        $min = 15;
        $max = 9999;
        $timestampInsercion = Carbon::now()->toDateTimeString();
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
            $m_phonecall->phonecall_id
        ];

        $ans = $this->fillTable($this->sheet['phonecall'], $rowData, $m_phonecall->phonecall_id);

        return response()->json([
            'message' => $ans['message']
        ], $ans['HTTPcode']);
    }
    public function updatePhonecall(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'fechaRegistro' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
            'cantidadLlamadas' => ['required', 'integer', 'min:0'],
            'ventasLogradas' => ['required', 'integer', 'min:0'],
            'tiempoPromedioLlamadas' => ['required', 'numeric', 'min:0'],
            'databaseId' => ['required', 'numeric', 'min:1'],
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
            'databaseId.required' => 'El campo ID es obligatorio.',
            'databaseId.numeric' => 'El campo ID debe ser un número.',
            'databaseId.min' => 'El campo ID no puede ser negativo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated(); // Obtener los datos validados

        $m_phonecall = dpb_phonecall::find($validatedData['databaseId']);
        $m_phonecall->phonecall_date = $validatedData['fechaRegistro'];
        $m_phonecall->phonecall_userid = $userId;
        $m_phonecall->phonecall_quantity = $validatedData['cantidadLlamadas'];
        $m_phonecall->phonecall_success = $validatedData['ventasLogradas'];
        $m_phonecall->phonecall_average = $validatedData['tiempoPromedioLlamadas'];
        $m_phonecall->phonecall_status = 1;
        $m_phonecall->save();

        // Prepara la fila con los datos actualizados para Google Sheets
        $min = 15;
        $max = 9999;
        $newRowData = [
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
            Carbon::now()->toDateTimeString(), 
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $m_phonecall->phonecall_id
        ];

        $ans = $this->updateTable($this->sheet['phonecall'], $validatedData['databaseId'], $newRowData);
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function showPhoneCallRecords(Request $request)
    {
        // 1. DETERMINACIÓN DEL PERÍODO Y FECHAS LÍMITE
        // 🚨 CAMBIO: Fecha por defecto es AYER (subDay()), a menos que se filtre
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        
        $carbonDate = Carbon::parse($filterDate);
        $year = $carbonDate->year;
        $month = $carbonDate->month;

        // Definir las fechas límite para la consulta de brechas
        $startDate = $carbonDate->firstOfMonth()->format('Y-m-d');
        $endDate = $carbonDate->lastOfMonth()->format('Y-m-d');
        $hoy = Carbon::now()->format('Y-m-d'); // Fecha actual para limitar la búsqueda

        // 2. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL
        $query = dpb_phonecall::query();

        // Aplicar filtro por mes/año
        $query->whereYear('phonecall_date', $year)
              ->whereMonth('phonecall_date', $month);

        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin');
        
        // El resto de la consulta principal (JOIN y GET) se maneja en el try-catch
        
        // 3. VERIFICACIÓN DE DÍAS FALTANTES (BRECHAS)

        // Condición de filtro de usuario para el LEFT JOIN
        $userIdCondition = $isAdmin ? "1=1" : "registros.phonecall_userid = " . $currentUserId;

        $dias_faltantes = DB::select("
            SELECT
                DATE_FORMAT(dias_del_mes.fecha_completa, '%d/%m') AS dia_sin_datos
            FROM
                (
                    -- Generar secuencia de fechas del mes consultado
                    SELECT DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY AS fecha_completa
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
                          UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                         (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) b
                    WHERE 
                        DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY <= '$endDate'
                        -- Excluir fechas futuras
                        AND DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY < '$hoy'
                ) AS dias_del_mes
            LEFT JOIN
                dpb_phonecalls AS registros -- 👈 Usamos el modelo dpb_phonecall
            ON
                dias_del_mes.fecha_completa = DATE(registros.phonecall_date)
                AND registros.phonecall_status = 1
                -- Mover el filtro de usuario al ON
                AND $userIdCondition
            WHERE
                registros.phonecall_date IS NULL
        ");

        $alerta_mensaje = null;

        if (count($dias_faltantes) > 0) {
            $dias_sin_datos = array_map(function($d){
                return $d->dia_sin_datos;
            }, $dias_faltantes);

            $dias_str = implode(', ', $dias_sin_datos);
            $alerta_mensaje = "Advertencia: Faltan datos de llamadas para los siguientes días del mes: " . $dias_str;
        }

        // 4. OBTENER REGISTROS FINALES Y RENDERIZAR VISTA
        try {
            if ($isAdmin) {
                $records = $query->ORDERBY('phonecall_status', 'DESC')->ORDERBY('phonecall_date', 'DESC')
                    ->JOIN('users', 'users.id', 'dpb_phonecalls.phonecall_userid')->GET();
            } else {
                $records = $query->ORDERBY('phonecall_status', 'DESC')->ORDERBY('phonecall_date', 'DESC')
                ->where('dpb_phonecalls.phonecall_userid',$currentUserId)
                ->JOIN('users', 'users.id', 'dpb_phonecalls.phonecall_userid')->GET();
            }
            
            $displayHeaders = [
                'phonecall_date' => 'Fecha',
                'name' => 'Usuario',
                'phonecall_quantity' => 'Cantidad',
                'phonecall_success' => 'Vnt. Logradas',
                'phonecall_average' => 'T. Promedio',
                'phonecall_status' => 'Estado'
            ];
            
            // Pasar el mensaje de alerta a la vista
            return view('forms.phonecall_records', compact('displayHeaders', 'records', 'alerta_mensaje'));
            
        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Llamadas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deletePhoneCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
        ], [
            'id.required' => 'El número de fila es obligatorio para la eliminación.',
            'id.integer' => 'El número de fila debe ser un número entero.',
            'id.min' => 'No existe el registro en ese rango.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Failed', 'errors' => $validator->errors()], 422);
        }

        $id = $request->input('id');

        $rs_phonecall = dpb_phonecall::find($id);
        $rs_phonecall->phonecall_status = 0;
        $rs_phonecall->save();

        $sheetName = $this->sheet['phonecall'];
        $result = $this->removeTable($sheetName, $id);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }


    public function showLaundryForm()
    {
        return view('forms.laundry_partial');
    }
    public function submitLaundry(Request $request)
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
            'fechaInicio.required' => 'El campo Fecha de Inicio es obligatorio.',
            'fechaInicio.date' => 'El campo Fecha de Inicio debe ser una fecha válida.',
            'fechaFin.required' => 'El campo Fecha de Fin es obligatorio.',
            'fechaFin.date' => 'El campo Fecha de Fin debe ser una fecha válida.',
            'fechaFin.after_or_equal' => 'La Fecha de Fin debe ser igual o posterior a la Fecha de Inicio.',
            'fechaFin.before_or_equal' => 'La Fecha de Fin no puede ser mayor a la fecha actual.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated();
        $userId = Auth::id();

        $qry_dateInside =   dpb_laundry::where('laundry_dateInit', ">", $validatedData['fechaInicio'])->where('laundry_dateFinish',"<", $validatedData['fechaFin'])->where('laundry_status',1);
        $qry_dateInit =     dpb_laundry::where('laundry_dateInit', "<", $validatedData['fechaInicio'])->where('laundry_dateFinish',">=", $validatedData['fechaInicio'])->where('laundry_status',1);

        $qry_dateFinish =   dpb_laundry::where('laundry_dateInit', "<=", $validatedData['fechaFin'])->where('laundry_dateFinish',">", $validatedData['fechaFin'])->where('laundry_status',1);
        $qry_dateuser =     dpb_laundry::where('laundry_dateInit', $validatedData['fechaInicio'])->where('laundry_dateFinish', $validatedData['fechaFin'])->where('laundry_status',1)->where('laundry_userid', $userId);
        $qry_date =         dpb_laundry::where('laundry_dateInit', $validatedData['fechaInicio'])->where('laundry_dateFinish', $validatedData['fechaFin'])->where('laundry_status',1);
        $qry_dateInitUpd =         dpb_laundry::where('laundry_dateInit', $validatedData['fechaInicio'])->where('laundry_status',1);
        $qry_dateFinishUpd =       dpb_laundry::where('laundry_dateFinish', $validatedData['fechaFin'])->where('laundry_status',1);

        if ($qry_dateInside->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Hay un registro dentro del rango de fechas ingresado.'], 500);
        }
        if ($qry_dateInit->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'La fecha de inicio existe dentro de un rango.'], 422);
        }
        if ($qry_dateFinish->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'La fecha de cierre existe dentro de un rango.'], 422);
        }        
        if ($qry_dateuser->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }
        if ($qry_date->count() > 0) {
            $validatedData['databaseId'] = $qry_date->first()->laundry_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }
        if ($qry_dateInitUpd->count() > 0) {
            $validatedData['databaseId'] = $qry_dateInitUpd->first()->laundry_id;
            return response()->json(['status' => 'confirm', 'message' => 'La fecha Inicio ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }
        if ($qry_dateFinishUpd->count() > 0) {
            $validatedData['databaseId'] = $qry_dateFinishUpd->first()->laundry_id;
            return response()->json(['status' => 'confirm', 'message' => 'La fecha Cierre ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }

        $m_laundry = new dpb_laundry;
        $m_laundry->laundry_dateInit = $validatedData['fechaInicio'];
        $m_laundry->laundry_dateFinish = $validatedData['fechaFin'];
        $m_laundry->laundry_userid = $userId;
        $m_laundry->laundry_total = $validatedData['totalGasto'];
        $m_laundry->laundry_cycle = $validatedData['cantidadCiclos'];
        $m_laundry->laundry_status = 1;
        $m_laundry->save();

        $min = 15;
        $max = 9999;
        $rowData = [
            mt_rand($min, $max), 
            mt_rand($min, $max), 
            mt_rand($min, $max), 
            mt_rand($min, $max),
            Auth::id(),          
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('fechaInicio'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('totalGasto'),
            mt_rand($min, $max),
            date('Y-m-d H:i:s'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('cantidadCiclos'),
            $request->input('fechaFin'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $m_laundry->laundry_id
        ];

        $ans = $this->fillTable($this->sheet['laundry'], $rowData, $m_laundry->laundry_id);

        return response()->json(['message' => $ans['message']], $ans['HTTPcode']);
    }
    public function updateLaundry(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'totalGasto' => ['required', 'numeric', 'min:0'],
            'cantidadCiclos' => ['required', 'integer', 'min:0'],
            'fechaInicio' => ['required', 'date'], // NUEVA REGLA
            'fechaFin' => ['required', 'date', 'after_or_equal:fechaInicio', 'before_or_equal:today'],
            'databaseId' => ['required', 'numeric', 'min:1'],
        ], [
            'totalGasto.required' => 'El campo Gasto Total es obligatorio.',
            'totalGasto.numeric' => 'El campo Gasto Total debe ser un número.',
            'totalGasto.min' => 'El campo Gasto Total no puede ser negativo.',
            'cantidadCiclos.required' => 'El campo Cantidad de Ciclos es obligatorio.',
            'cantidadCiclos.integer' => 'El campo Cantidad de Ciclos debe ser un número entero.',
            'cantidadCiclos.min' => 'El campo Cantidad de Ciclos no puede ser negativo.',
            'fechaInicio.required' => 'El campo Fecha de Inicio es obligatorio.',
            'fechaInicio.date' => 'El campo Fecha de Inicio debe ser una fecha válida.',
            'fechaFin.required' => 'El campo Fecha de Fin es obligatorio.',
            'fechaFin.date' => 'El campo Fecha de Fin debe ser una fecha válida.',
            'fechaFin.after_or_equal' => 'La Fecha de Fin debe ser igual o posterior a la Fecha de Inicio.',
            'fechaFin.before_or_equal' => 'La Fecha de Fin no puede ser mayor a la fecha actual.',
            'databaseId.required' => 'El campo ID es obligatorio.',
            'databaseId.numeric' => 'El campo ID debe ser un número.',
            'databaseId.min' => 'El campo ID no puede ser negativo.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated(); // Obtener los datos validados

        $m_laundry = dpb_laundry::find($validatedData['databaseId']);
        $m_laundry->laundry_dateInit = $validatedData['fechaInicio'];
        $m_laundry->laundry_dateFinish = $validatedData['fechaFin'];
        $m_laundry->laundry_userid = $userId;
        $m_laundry->laundry_total = $validatedData['totalGasto'];
        $m_laundry->laundry_cycle = $validatedData['cantidadCiclos'];
        $m_laundry->laundry_status = 1;
        $m_laundry->save();

        $min = 15;
        $max = 9999;
        $newRowData = [
            mt_rand($min, $max), 
            mt_rand($min, $max), 
            mt_rand($min, $max), 
            mt_rand($min, $max),
            Auth::id(),          
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('fechaInicio'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('totalGasto'),
            mt_rand($min, $max),
            date('Y-m-d H:i:s'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $request->input('cantidadCiclos'),
            $request->input('fechaFin'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            $m_laundry->laundry_id
        ];

        $ans = $this->updateTable($this->sheet['laundry'], $validatedData['databaseId'], $newRowData);
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function showLaundryRecords(Request $request)
    {
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        $year = Carbon::parse($filterDate)->year;
        $month = Carbon::parse($filterDate)->month;

        $query = dpb_laundry::query();

        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('laundry_dateInit', "<=", $year)->whereYear('laundry_dateFinish', ">=", $year)
                  ->whereMonth('laundry_dateInit', "<=", $month)->whereMonth('laundry_dateFinish', ">=", $month);
        } else {
            $query->whereYear('laundry_dateInit', "<=", Carbon::now()->year)->whereYear('laundry_dateFinish', ">=", Carbon::now()->year)
                  ->whereMonth('laundry_dateInit', "<=", Carbon::now()->month)->whereMonth('laundry_dateFinish', ">=", Carbon::now()->month);

        }
        
        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin');

        try {
            if ($isAdmin) {
                $records = $query->orderBy('laundry_status', 'DESC')->orderBy('laundry_dateInit', 'DESC')
                    ->join('users', 'users.id', 'dpb_laundries.laundry_userid')->get();
            } else {
                $records = $query->orderBy('laundry_status', 'DESC')->orderBy('laundry_dateInit', 'DESC')
                ->where('dpb_laundries.laundry_userid', $currentUserId)
                ->join('users', 'users.id', 'dpb_laundries.laundry_userid')->get();
            }
            
            $displayHeaders = [
                'laundry_dateInit' => 'Fecha Inicio',
                'laundry_dateFinish' => 'Fecha Cierre',
                'name' => 'Usuario',
                'laundry_total' => 'Gasto Total',
                'laundry_cycle' => 'Ciclos',
                'laundry_status' => 'Estado'
            ];

            return view('forms.laundry_records', compact('displayHeaders', 'records', 'filterDate'));
        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Lavandería: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteLaundry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
        ], [
            'id.required' => 'El número de fila es obligatorio para la eliminación.',
            'id.integer' => 'El número de fila debe ser un número entero.',
            'id.min' => 'No se puede eliminar la fila 0.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $id = $request->input('id');

        $rs_laundry = dpb_laundry::find($id);
        $rs_laundry->laundry_status = 0;
        $rs_laundry->save();

        $sheetName = $this->sheet['laundry'];
        $result = $this->removeTable($sheetName, $id);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }


    public function showInventoryHkForm()
    {
        return view('forms.inventoryhk_partial');
    }
    public function submitInventoryHk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaRegistro' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
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
            'fechaRegistro.required' => 'La fecha del inventario es obligatoria.',
            'fechaRegistro.date_format' => 'El formato de la fecha no es válido (debe ser AAAA-MM-DD).',
            'fechaRegistro.before_or_equal' => 'La fecha del inventario no puede ser futura.',
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
        $validatedData = $validator->validated();
        $userId = Auth::id();

        $qry_dateuser = dpb_inventoryhk::where('inventoryhk_date', $validatedData['fechaRegistro'])->where('inventoryhk_userid', $userId)->where('inventoryhk_status',1);
        if ($qry_dateuser->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }

        $qry_date = dpb_inventoryhk::where('inventoryhk_date', $validatedData['fechaRegistro'])->where('inventoryhk_status',1);
        if ($qry_date->count() > 0) {
            $validatedData['databaseId'] = $qry_date->first()->inventoryhk_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }

        $m_inventoryhk = new dpb_inventoryhk;
        $m_inventoryhk->inventoryhk_date        = $validatedData['fechaRegistro'];
        $m_inventoryhk->inventoryhk_userid      = $userId;
        $m_inventoryhk->inventoryhk_kSheet          = $validatedData['sheet_k'];
        $m_inventoryhk->inventoryhk_qSheet          = $validatedData['sheet_q'];
        $m_inventoryhk->inventoryhk_kPillowcase     = $validatedData['pillowcase_k'];
        $m_inventoryhk->inventoryhk_qPillowcase     = $validatedData['pillowcase_q'];
        $m_inventoryhk->inventoryhk_kPillow         = $validatedData['pillow_k'];
        $m_inventoryhk->inventoryhk_qPillow         = $validatedData['pillow_q'];
        $m_inventoryhk->inventoryhk_kMattressprotector    = $validatedData['mattressprotector_k'];
        $m_inventoryhk->inventoryhk_qMattressprotector    = $validatedData['mattressprotector_q'];
        $m_inventoryhk->inventoryhk_towel           = $validatedData['towel_blank'];
        $m_inventoryhk->inventoryhk_handTowel       = $validatedData['hand_towel'];
        $m_inventoryhk->inventoryhk_feetTowel       = $validatedData['foot_towel'];
        $m_inventoryhk->inventoryhk_faceTowel       = $validatedData['face_towel'];
        $m_inventoryhk->inventoryhk_poolTowel       = $validatedData['towel_blue'];
        $m_inventoryhk->inventoryhk_blueBlanket     = $validatedData['blanket_blue'];
        $m_inventoryhk->inventoryhk_greenBlanket    = $validatedData['blanket_green'];
        $m_inventoryhk->inventoryhk_kDuvet          = $validatedData['duveth_k'];
        $m_inventoryhk->inventoryhk_qDuvet          = $validatedData['duveth_q'];
        $m_inventoryhk->inventoryhk_kCover          = $validatedData['cover_k'];
        $m_inventoryhk->inventoryhk_qCover          = $validatedData['cover_q'];
        $m_inventoryhk->inventoryhk_kBedskirt       = $validatedData['bedskirt_k'];
        $m_inventoryhk->inventoryhk_qBedskirt       = $validatedData['bedskirt_q'];
        $m_inventoryhk->inventoryhk_status = 1;
        $m_inventoryhk->save();

        $rowData = [
            $userId,                                     // Columna A (ej. user_id)
            $request->input('fechaRegistro'),          // Columna C (ej. Fecha del Inventario)
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
            $m_inventoryhk->inventoryhk_id
        ];
        $ans = $this->fillTable($this->sheet['inventoryhk'], $rowData, $m_inventoryhk->inventoryhk_id);
        return response()->json(['message' => $ans['message']], $ans['HTTPcode']);
    }
    public function updateInventoryhk(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'fechaRegistro' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . Carbon::now()->format('Y-m-d')],
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
            'databaseId' => ['required', 'numeric', 'min:1'],
        ], [
            'fechaRegistro.required' => 'La fecha del inventario es obligatoria.',
            'fechaRegistro.date_format' => 'El formato de la fecha no es válido (debe ser AAAA-MM-DD).',
            'fechaRegistro.before_or_equal' => 'La fecha del inventario no puede ser futura.',
            'databaseId.required' => 'El campo ID es obligatorio.',
            'databaseId.numeric' => 'El campo ID debe ser un número.',
            'databaseId.min' => 'El campo ID no puede ser negativo.',
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
        $validatedData = $validator->validated();

        $m_inventoryhk = dpb_inventoryhk::find($validatedData['databaseId']);
        $m_inventoryhk->inventoryhk_date        = $validatedData['fechaRegistro'];
        $m_inventoryhk->inventoryhk_userid      = $userId;
        $m_inventoryhk->inventoryhk_kSheet          = $validatedData['sheet_k'];
        $m_inventoryhk->inventoryhk_qSheet          = $validatedData['sheet_q'];
        $m_inventoryhk->inventoryhk_kPillowcase     = $validatedData['pillowcase_k'];
        $m_inventoryhk->inventoryhk_qPillowcase     = $validatedData['pillowcase_q'];
        $m_inventoryhk->inventoryhk_kPillow         = $validatedData['pillow_k'];
        $m_inventoryhk->inventoryhk_qPillow         = $validatedData['pillow_q'];
        $m_inventoryhk->inventoryhk_kMattressprotector    = $validatedData['mattressprotector_k'];
        $m_inventoryhk->inventoryhk_qMattressprotector    = $validatedData['mattressprotector_q'];
        $m_inventoryhk->inventoryhk_towel           = $validatedData['towel_blank'];
        $m_inventoryhk->inventoryhk_handTowel       = $validatedData['hand_towel'];
        $m_inventoryhk->inventoryhk_feetTowel       = $validatedData['foot_towel'];
        $m_inventoryhk->inventoryhk_faceTowel       = $validatedData['face_towel'];
        $m_inventoryhk->inventoryhk_poolTowel       = $validatedData['towel_blue'];
        $m_inventoryhk->inventoryhk_blueBlanket     = $validatedData['blanket_blue'];
        $m_inventoryhk->inventoryhk_greenBlanket    = $validatedData['blanket_green'];
        $m_inventoryhk->inventoryhk_kDuvet          = $validatedData['duveth_k'];
        $m_inventoryhk->inventoryhk_qDuvet          = $validatedData['duveth_q'];
        $m_inventoryhk->inventoryhk_kCover          = $validatedData['cover_k'];
        $m_inventoryhk->inventoryhk_qCover          = $validatedData['cover_q'];
        $m_inventoryhk->inventoryhk_kBedskirt       = $validatedData['bedskirt_k'];
        $m_inventoryhk->inventoryhk_qBedskirt       = $validatedData['bedskirt_q'];
        $m_inventoryhk->inventoryhk_status = 1;
        $m_inventoryhk->save();

        // Prepara la fila con los datos actualizados para Google Sheets
        $newRowData = [
            $userId,                                     // Columna A (ej. user_id)
            $request->input('fechaRegistro'),          // Columna C (ej. Fecha del Inventario)
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
            $m_inventoryhk->inventoryhk_id
        ];

        $ans = $this->updateTable($this->sheet['inventoryhk'], $validatedData['databaseId'], $newRowData);
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function showInventoryHkRecords(Request $request)
    {
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        $year = Carbon::parse($filterDate)->year;
        $month = Carbon::parse($filterDate)->month;

        $query = dpb_inventoryhk::query();

        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('inventoryhk_date', $year)
                  ->whereMonth('inventoryhk_date', $month);
        } else {
            $query->whereYear('inventoryhk_date', Carbon::now()->year)
                  ->whereMonth('inventoryhk_date', Carbon::now()->month);
        }
        
        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin');

        try {
            if ($isAdmin) {
                $records = $query->orderBy('inventoryhk_status', 'DESC')->orderBy('inventoryhk_date', 'DESC')
                    ->join('users', 'users.id', 'dpb_inventoryhks.inventoryhk_userid')->get();
            } else {
                $records = $query->orderBy('inventoryhk_status', 'DESC')->orderBy('inventoryhk_date', 'DESC')
                ->where('dpb_inventoryhks.inventoryhk_userid', $currentUserId)
                ->join('users', 'users.id', 'dpb_inventoryhks.inventoryhk_userid')->get();
            }
            
            $displayHeaders = [
                'inventoryhk_date' => 'Fecha',
                'name' => 'Usuario',
                'inventoryhk_kSheet' => 'Sabanas K',
                'inventoryhk_qSheet' => 'Sabanas Q',
                'inventoryhk_kPillowcase' => 'Funda K',
                'inventoryhk_qPillowcase' => 'Funda Q',
                'inventoryhk_kPillow' => 'Almohada K',
                'inventoryhk_qPillow' => 'Almohada Q',
                'inventoryhk_kMattressprotector' => 'Protector K',
                'inventoryhk_qMattressprotector' => 'Protector Q',
                'inventoryhk_towel' => 'Toalla',
                'inventoryhk_handTowel' => 'Toalla p/Mano',
                'inventoryhk_feetTowel' => 'Toalla p/Pies',
                'inventoryhk_faceTowel' => 'Toalla p/Rostro',
                'inventoryhk_poolTowel' => 'Toalla Piscina',
                'inventoryhk_blueBlanket' => 'Frazada Azul',
                'inventoryhk_greenBlanket' => 'Frazada Verde',
                'inventoryhk_kDuvet' => 'Duvet K',
                'inventoryhk_qDuvet' => 'Duvet Q',
                'inventoryhk_kCover' => 'Cover K',
                'inventoryhk_qCover' => 'Cover Q',
                'inventoryhk_kBedskirt' => 'Faldon K',
                'inventoryhk_qBedskirt' => 'Faldon Q',
                'inventoryhk_status' => 'Estado'
            ];
            return view('forms.inventoryhk_records', compact('displayHeaders', 'records', 'filterDate'));
        } catch (\Exception $e) {
            Log::error('Error al cargar registros de Lavandería: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteInventoryHk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'], // Mínimo 2 porque la fila 1 son encabezados
        ], [
            'id.required' => 'El número de fila es obligatorio para la eliminación.',
            'id.integer' => 'El número de fila debe ser un número entero.',
            'id.min' => 'No se puede eliminar la fila 0.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $id = $request->input('id');

        $rs_inventoryhk = dpb_inventoryhk::find($id);
        $rs_inventoryhk->inventoryhk_status = 0;
        $rs_inventoryhk->save();

        $sheetName = $this->sheet['inventoryhk'];
        $result = $this->removeTable($sheetName, $id);
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }



    public function showAuditorForm()
    {
        return view('forms.auditor_partial');
    }
    public function submitAuditor(Request $request)
    {
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
        $validatedData = $validator->validated();

        $userId = Auth::id();
        $qry_dateuser = dpb_income::where('income_date', $validatedData['fechaRegistro'])->where('income_userid', $userId)->where('income_status',1);
        if ($qry_dateuser->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Registro ya existe.'], 422);                
        }

        $qry_date = dpb_income::where('income_date', $validatedData['fechaRegistro'])->where('income_status',1);
        if ($qry_date->count() > 0) {
            $validatedData['databaseId'] = $qry_date->first()->income_id;
            return response()->json(['status' => 'confirm', 'message' => 'Registro ya existe, ¿Desea sobreescribirlo?.', 'data' => $validatedData], 200);
        }

        $m_income = new dpb_income;
        $m_income->income_date      = $validatedData['fechaRegistro'];
        $m_income->income_ab        = $validatedData['montoAB'];
        $m_income->income_other     = $validatedData['montoOtro'];
        $m_income->income_userid    = $userId;
        $m_income->income_status    = 1;
        $m_income->save();

        $ans = $this->fillTableSale($validatedData['fechaRegistro']);
        /*
        $min = 15;
        $max = 9999;
        $userId = Auth::id(); // ID del usuario autenticado
        $rs_data = dpb_sale::WHERE('sale_date',$validatedData['fechaRegistro'])->JOIN('dpb_incomes','dpb_incomes.income_date',"=",'dpb_sales.sale_date')->first();
        $rowData = [
            mt_rand($min, $max),
            !empty($rs_data['sale_corporative']) ? $rs_data['sale_corporative'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_national']) ? $rs_data['sale_national'] : 0,
            mt_rand($min, $max),
            $m_income->income_other,
            $userId,
            $m_income->income_ab,
            !empty($rs_data['sale_callcenter']) ? $rs_data['sale_callcenter'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_ota']) ? $rs_data['sale_ota'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_international']) ? $rs_data['sale_international'] : 0,
            !empty($rs_data['sale_arenas']) ? $rs_data['sale_arenas'] : 0,
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_data['created_at']) ? $rs_data['created_at'] : date('Y-m-d H:i:s'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_data['sale_web']) ? $rs_data['sale_web'] : 0,
            mt_rand($min, $max),
            $request->input('fechaRegistro'),
            $m_income->income_id,
            !empty($rs_data['sale_id']) ? $rs_data['sale_id'] : 0
        ];
        $ans = $this->fillTableSale($rowData, $request->input('fechaRegistro'));*/
        return response()->json(['status' => $ans['status'], 'message' => $ans['message']], $ans['HTTPcode']);
    }
    public function updateAuditor(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'montoAB' => ['required', 'numeric', 'min:0'],
            'montoOtro' => ['required', 'numeric', 'min:0'],
            'fechaRegistro' => ['required', 'date', 'before_or_equal:today'], // La fecha no puede ser futura
            'databaseId' => ['required', 'numeric', 'min:1'],
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
            'databaseId.required' => 'El campo ID es obligatorio.',
            'databaseId.numeric' => 'El campo ID debe ser un número.',
            'databaseId.min' => 'El campo ID no puede ser negativo.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated();

        $m_income = dpb_income::find($validatedData['databaseId']);
        $m_income->income_ab        = $validatedData['montoAB'];
        $m_income->income_other     = $validatedData['montoOtro'];
        $m_income->income_userid    = $userId;
        $m_income->income_status    = 1;
        $m_income->save();

        $ans = $this->fillTableSale($validatedData['fechaRegistro']);
        /*
        // Prepara la fila con los datos actualizados para Google Sheets
        $min = 15;
        $max = 9999;
        $rs_data = dpb_sale::WHERE('sale_date',$validatedData['fechaRegistro'])->JOIN('dpb_incomes','dpb_incomes.income_date',"=",'dpb_sales.sale_date')->first();
        $rowData = [
            mt_rand($min, $max),
            !empty($rs_data['sale_corporative']) ? $rs_data['sale_corporative'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_national']) ? $rs_data['sale_national'] : 0,
            mt_rand($min, $max),
            $m_income->income_other,
            $userId,
            $m_income->income_ab,
            !empty($rs_data['sale_callcenter']) ? $rs_data['sale_callcenter'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_ota']) ? $rs_data['sale_ota'] : 0,
            mt_rand($min, $max),
            !empty($rs_data['sale_international']) ? $rs_data['sale_international'] : 0,
            !empty($rs_data['sale_arenas']) ? $rs_data['sale_arenas'] : 0,
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_data['created_at']) ? $rs_data['created_at'] : date('Y-m-d H:i:s'),
            mt_rand($min, $max),
            mt_rand($min, $max),
            !empty($rs_data['sale_web']) ? $rs_data['sale_web'] : 0,
            mt_rand($min, $max),
            $request->input('fechaRegistro'),
            $validatedData['databaseId'],
            !empty($rs_data['sale_id']) ? $rs_data['sale_id'] : 0
        ];
        $ans = $this->fillTableSale($rowData,$validatedData['fechaRegistro']);
        */
        if ($ans['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $ans['message']], $ans['HTTPcode']);
        } else {
            return response()->json(['status' => 'fail', 'message' => $ans['message']], $ans['HTTPcode']);
        }
    }
    public function showAuditorRecords(Request $request)
    {
        // 1. DETERMINACIÓN DEL PERÍODO Y FECHAS LÍMITE
        // 🚨 CORRECCIÓN: Usar AYER (subDay()) como fecha por defecto, según solicitud anterior.
        $filterDate = $request->input('filter_date', Carbon::now()->format('Y-m-d'));
        
        $carbonDate = Carbon::parse($filterDate);
        $year = $carbonDate->year;
        $month = $carbonDate->month;

        // Definir las fechas límite para las consultas
        $startDate = $carbonDate->firstOfMonth()->format('Y-m-d');
        $endDate = $carbonDate->lastOfMonth()->format('Y-m-d');
        $hoy = Carbon::now()->format('Y-m-d');

        // ... (Sección 2: CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL - Se mantiene igual)
        $query = dpb_income::query();

        if ($request->has('filter_date') && $request->input('filter_date')) {
            $query->whereYear('income_date', $year)->whereMonth('income_date', $month);
        } else {
            $query->whereYear('income_date', Carbon::now()->year)->whereMonth('income_date', Carbon::now()->month);
        }
        
        $currentUser = Auth::user();
        $currentUserId = $currentUser->id;
        $isAdmin = $currentUser->hasRole('Admin');

        // 3. VERIFICACIÓN DE DÍAS FALTANTES (BRECHAS DE FECHAS)
        $userIdConditionBrecha = $isAdmin ? "1=1" : "registros.income_userid = " . $currentUserId;

        $dias_faltantes = DB::select("
            SELECT
                DATE_FORMAT(dias_del_mes.fecha_completa, '%d/%m') AS dia_sin_datos
            FROM
                (
                    SELECT DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY AS fecha_completa
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
                          UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                         (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) b
                    WHERE 
                        DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY <= '$endDate'
                        -- Usamos '<=' en lugar de '<' para incluir el día de hoy si aún no tiene registro, 
                        AND DATE('$startDate') + INTERVAL (a.N + b.N*10) DAY < '$hoy'
                ) AS dias_del_mes
            LEFT JOIN
                dpb_incomes AS registros
            ON
                dias_del_mes.fecha_completa = DATE(registros.income_date)
                AND registros.income_status = 1 
                AND $userIdConditionBrecha 
            WHERE
                registros.income_date IS NULL
        ");

        $alerta_brechas = null;
        if (count($dias_faltantes) > 0) {
            $dias_str = implode(', ', array_map(fn($d) => $d->dia_sin_datos, $dias_faltantes));
            $alerta_brechas = "Faltan registros de ingresos ACTIVOS para los días: " . $dias_str;
        }

        // 4. VERIFICACIÓN DE EQUIVALENCIA INVERSA (Ingresos vs Ventas) 🚨 NUEVO CÓDIGO 🚨
        
        // Condición de usuario para la consulta SQL de equivalencia
        $userIdConditionEq = $isAdmin ? "" : " AND T1.income_userid = " . $currentUserId;
        
        $registros_sin_equivalencia = DB::select("
            SELECT
                DATE_FORMAT(T1.income_date, '%d/%m') AS fecha_sin_equivalencia
            FROM
                dpb_incomes AS T1 -- 👈 Tabla de Ingresos (fuente)
            LEFT JOIN
                dpb_sales AS T2 -- 👈 Tabla de Ventas (destino/equivalente)
            ON
                DATE(T1.income_date) = DATE(T2.sale_date)
                -- Solo consideramos equivalencia si el registro de ventas también está activo
                AND T2.sale_status = 1 
            WHERE
                -- T2 es NULL: No hay registro de venta ACTIVO que coincida
                T2.sale_date IS NULL
                -- Solo revisamos registros de ingresos activos
                AND T1.income_status = 1
                -- Aplicar filtro de mes/año
                AND YEAR(T1.income_date) = $year
                AND MONTH(T1.income_date) = $month
                -- Aplicar filtro de usuario
                $userIdConditionEq
            GROUP BY
                fecha_sin_equivalencia
        ");

        $alerta_equivalencia = null;
        if (count($registros_sin_equivalencia) > 0) {
            $fechas_str = implode(', ', array_map(fn($r) => $r->fecha_sin_equivalencia, $registros_sin_equivalencia));
            $alerta_equivalencia = "Registros de ingresos activos sin un registro de ventas activo equivalente en las fechas: " . $fechas_str;
        }
        
        // 5. COMBINAR MENSAJES DE ALERTA
        $alerta_final = null;
        if ($alerta_brechas && $alerta_equivalencia) {
            $alerta_final = "Brechas: " . $alerta_brechas . " <br/> Equivalencia: " . $alerta_equivalencia;
        } elseif ($alerta_brechas) {
            $alerta_final = $alerta_brechas;
        } elseif ($alerta_equivalencia) {
            $alerta_final = $alerta_equivalencia;
        }


        // 6. OBTENER REGISTROS FINALES Y RENDERIZAR VISTA
        try {
            if ($isAdmin) {
                $records = $query->orderBy('income_status', 'DESC')->orderBy('income_date', 'DESC')
                    ->join('users', 'users.id', 'dpb_incomes.income_userid')->get();
            } else {
                $records = $query->orderBy('income_status', 'DESC')->orderBy('income_date', 'DESC')
                ->where('dpb_incomes.income_userid', $currentUserId)
                ->join('users', 'users.id', 'dpb_incomes.income_userid')->get();
            }
            
            $displayHeaders = [
                'income_date' => 'Fecha',
                'name' => 'Usuario',
                'income_ab' => 'Ingresos A&B',
                'income_other' => 'Otros Ingresos',
                'income_status' => 'Estado'
            ];

            // 7. Pasar el mensaje de alerta final a la vista
            return view('forms.auditor_records', compact('displayHeaders', 'records', 'filterDate', 'alerta_final'));

        } catch (\Exception $e) {
            Log::error('Error al cargar registros: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }
    public function deleteAuditor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'row_number' => ['required', 'integer', 'min:1'],
        ], [
            'row_number.required' => 'El número de fila es obligatorio para la eliminación.',
            'row_number.integer' => 'El número de fila debe ser un número entero.',
            'row_number.min' => 'No se puede eliminar la fila 0.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated(); // Obtener los datos validados

        $rs_income = dpb_income::find($validatedData['row_number']);
        $rs_income->income_status = 0;
        $rs_income->save();

        $result = $this->removeTableSale(date('Y-m-d', strtotime($rs_income['income_date'])));
        return response()->json(['message' => $result['message']], $result['HTTPcode']);
    }



    public function filterUser(Request $request)
    {
        $filter_user = $request->input('filter_user');

        $rs_user = User::WHERE('name','LIKE','%'.$filter_user.'%')->orWhere('email','LIKE','%'.$filter_user.'%')->get();

        try {
            $displayHeaders = [
                'name' => 'Usuario',
                'email' => 'Correo',
                'user_status' => 'Estado',
            ];
            return view('auth.partial.user_records', compact('displayHeaders','rs_user', 'filter_user'));
        } catch (\Exception $e) {
            Log::error('Error al cargar registros: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cargar registros: ' . $e->getMessage()], 500);
        }
    }

}