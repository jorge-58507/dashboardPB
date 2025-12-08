{{-- resources/views/forms/inventory/auditor_records.blade.php --}}
 
<div class="bg-white p-6 rounded-lg shadow-lg">
    @if (isset($alerta_final) && $alerta_final)
        <div id="atencion-div" class="p-4 mb-4 text-sm text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-300 dark:border-red-800" role="alert">
            <div class="flex items-center">
                <span class="text-xl mr-2" aria-hidden="true">&#9888;</span> 
                <h3 class="text-lg font-medium">¡Alerta de Consistencia de Ingresos/Ventas!</h3>
            </div>
            <div class="mt-2 text-sm ml-6">
                <p class="font-semibold">{{ $alerta_final }}</p>
            </div>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-dark-navy mb-6">Registros de Ingresos</h2>
        <form id="income-filter-form" action="{{ route('formulario.auditor.records') }}" method="GET" onsubmit="event.preventDefault();" class="mt-4 lg:mt-0">
            <div class="flex items-center gap-2">
                <input type="date" id="filter_date" name="filter_date" value="{{ $filterDate ?? \Carbon\Carbon::now()->format('Y-m-d') }}"
                    class="shadow appearance-none border border-light-gray rounded py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
                <button type="submit" class="bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded">
                    Filtrar
                </button>
            </div>
        </form>
    </div>
    @if(empty($records))
        <p class="text-gray-600">No hay registros de otros ingresos para mostrar.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        @foreach($displayHeaders as $header) {{-- Usamos los nuevos encabezados personalizados --}}
                            <th class="py-2 px-4 border-b">{{ $header }}</th>
                        @endforeach
                        <th class="py-2 px-4 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $i => $record)
                        <tr id="row-income-{{ $record['income_id'] }}" class="{{ ($record['income_status'] === 0) ? 'bg-gray-700 text-white' : '' }}">
                            @foreach($displayHeaders as $colIndex => $headerName)
                                @switch($colIndex)
                                    @case('income_date')
                                        <td class="py-2 px-4 border-b">{{date('d-m-Y', strtotime($record[$colIndex]))}}</td>
                                        @break
                                    @case('income_status')
                                        <td class="py-2 px-4 border-b">{{ ($record[$colIndex] === 1) ? 'Activo' : 'Inactivo' }}</td>
                                        @break                                        
                                    @default
                                        <td class="py-2 px-4 border-b">{{ $record[$colIndex] ?? '' }}</td>                                    
                                @endswitch    
                            @endforeach
                            <td class="py-2 px-4 border-b">
                                @if ($record['income_status'] === 1)
                                    <button class="delete-income-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                            data-row-number="{{ $record['income_id'] }}">
                                        Eliminar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @endif
</div>