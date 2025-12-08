<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    //return view('welcome');
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/userList-filter', [formController::class, 'filterUser']);
    

    // --- GRUPO DE RUTAS PARA TODOS LOS FORMULARIOS ---
    Route::prefix('formulario')->name('formulario.')->group(function () {

        // Rutas para Consumo de Gas
        Route::get('/gas-consumption-partial', [FormController::class, 'getGasConsumptionFormPartial'])
            ->middleware('role:Admin|Mantenimiento')
            ->name('gas_consumption_partial');
        Route::post('/submit-gas', [FormController::class, 'submitGasToSheet'])
            ->middleware(['role:Admin|Mantenimiento'])
            ->name('gas.submit'); // Nombre de ruta simplificado
        Route::put('/submit-gas', [FormController::class, 'updateGas'])
            ->middleware(['role:Admin|Mantenimiento']);
            
        Route::get('/gas-consumption-records', [FormController::class, 'showGasConsumptionRecords'])
            ->middleware(['role:Admin|Mantenimiento'])
            ->name('gasConsumption.records');
        Route::delete('/delete-gas-consumption', [FormController::class, 'deleteGasConsumption'])
            ->middleware(['role:Admin|Mantenimiento'])
            ->name('gasConsumption.delete');

        // Rutas para Ventas
        Route::get('/sales-partial', [FormController::class, 'showSalesForm'])
            ->name('sales_partial');
        Route::post('/submit-sales', [FormController::class, 'submitSales'])
            ->middleware(['role:Admin|Ventas'])
            ->name('sales.submit');
        Route::put('/submit-sales', [FormController::class, 'updateSales'])
            ->middleware(['role:Admin|Ventas']);
        Route::get('/sales-records', [FormController::class, 'showSalesRecords'])
            ->middleware(['role:Admin|Ventas'])
            ->name('sales.records');
        Route::delete('/delete-sales', [FormController::class, 'deleteSales'])
            ->middleware(['role:Admin|Ventas'])
            ->name('sales.delete');

        // Rutas para Registro Diario de Llamadas
        Route::get('/phonecall-partial', [FormController::class, 'showPhoneCallForm'])
            ->name('phonecall_partial');
        Route::post('/submit-phonecall', [FormController::class, 'submitPhoneCall'])
            ->middleware(['role:Admin|Llamadas|Ventas']) // Considera si 'Ventas' necesita acceso a esto
            ->name('phonecall.submit');
        Route::put('/submit-phonecall', [FormController::class, 'updatePhonecall'])
            ->middleware(['role:Admin|Llamadas|Ventas']);
        Route::get('/phonecall-records', [FormController::class, 'showPhoneCallRecords'])
            ->middleware(['role:Admin|Llamadas|Ventas']) // Considera si 'Ventas' necesita acceso a esto
            ->name('phonecall.records');
        Route::delete('/delete-phonecall', [FormController::class, 'deletePhoneCall'])
            ->middleware(['role:Admin|Llamadas|Ventas']) // Considera si 'Ventas' necesita acceso a esto
            ->name('phonecall.delete');

        // Rutas para Lavandería
        Route::get('/laundry-partial', [FormController::class, 'showLaundryForm'])
            ->name('laundry_partial');
        Route::post('/submit-laundry', [FormController::class, 'submitLaundry'])
            ->middleware(['role:Admin|Lavanderia|Housekeeping'])
            ->name('laundry.submit');
        Route::put('/submit-laundry', [FormController::class, 'updateLaundry'])
            ->middleware(['role:Admin|Lavanderia|Housekeeping']);
        Route::get('/laundry-records', [FormController::class, 'showLaundryRecords'])
            ->middleware(['role:Admin|Lavanderia|Housekeeping'])
            ->name('laundry.records');
        Route::delete('/delete-laundry', [FormController::class, 'deleteLaundry'])
            ->middleware(['role:Admin|Lavanderia|Housekeeping'])
            ->name('laundry.delete');

        // --- NUEVAS RUTAS PARA INVENTARIO DE HOUSEKEEPING (HK) ---
        Route::get('/inventoryhk-partial', [FormController::class, 'showInventoryHkForm'])
            ->name('inventoryhk_partial');
        Route::post('/submit-inventoryhk', [FormController::class, 'submitInventoryHk'])
            ->middleware(['role:Admin|Housekeeping'])
            ->name('inventoryhk.submit');
        Route::put('/submit-inventoryhk', [FormController::class, 'updateInventoryhk'])
            ->middleware(['role:Admin|Housekeeping']);
        Route::get('/inventoryhk-records', [FormController::class, 'showInventoryHkRecords'])
            ->middleware(['role:Admin|Housekeeping'])
            ->name('inventoryhk.records');
        Route::delete('/delete-inventoryhk', [FormController::class, 'deleteInventoryHk'])
            ->middleware(['role:Admin|Housekeeping'])
            ->name('inventoryhk.delete');


        // Rutas para Auditor
        Route::get('/auditor-partial', [FormController::class, 'showAuditorForm'])
            ->name('auditor_partial');
        Route::post('/submit-auditor', [FormController::class, 'submitAuditor'])
            ->middleware(['role:Admin|Auditor'])
            ->name('auditor.submit');
        Route::put('/submit-auditor', [FormController::class, 'updateAuditor'])
            ->middleware(['role:Admin|Auditor']);
        Route::get('/auditor-records', [FormController::class, 'showAuditorRecords'])
            ->middleware(['role:Admin|Auditor'])
            ->name('auditor.records');
        Route::delete('/delete-auditor', [FormController::class, 'deleteAuditor'])
            ->middleware(['role:Admin|Auditor'])
            ->name('auditor.delete');



    });
});



/*
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::get('/forms/gas-consumption-partial', [App\Http\Controllers\FormController::class, 'getGasConsumptionFormPartial'])
        ->middleware('role:Admin|Mantenimiento') // Proteger acceso a la carga del HTML parcial también
        ->name('forms.gas_consumption_partial');
    Route::get('/forms/laundry', [FormController::class, 'showLaundryForm'])->name('forms.laundry_partial'); // NUEVA RUTA PARA CARGAR EL FORMULARIO
    Route::get('/forms/sales', [FormController::class, 'showSalesForm'])->name('forms.sales_partial');
    Route::get('/phonecall-partial', [FormController::class, 'showPhoneCallForm'])->name('phonecall_partial');



    // Ruta para enviar el formulario 
    Route::post('/fillsheetF', [App\Http\Controllers\FormController::class, 'submitGasToSheet'])
        ->middleware(['role:Admin|Mantenimiento']) // Sigue protegida con 'Admin' o 'mantenimiento'
        ->name('formulario.gas.submit');
    Route::post('/fillsheetG', [FormController::class, 'submitLaundry'])
        ->middleware(['role:Admin|Lavanderia|Housekeeping']) // Sigue protegida con 'Admin' o 'Lavandería y Housekeeping'
        ->name('formulario.laundry.submit'); // NUEVA RUTA PARA ENVIAR DATOS
    Route::post('/submit-sales', [FormController::class, 'submitSales'])
        ->middleware(['role:Admin|Ventas']) // Sigue protegida con 'Admin' o 'Ventas'
        ->name('formulario.sales.submit');
    Route::post('/submit-phonecall', [FormController::class, 'submitPhoneCall'])
        ->middleware(['role:Admin|Llamadas|Ventas']) // Sigue protegida con 'Admin' o 'Ventas'
        ->name('formulario.phonecall.submit');

});
*/
require __DIR__.'/auth.php';