{{-- resources/views/forms/gas_consumption_records.blade.php --}}  

<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-dark-navy w-full lg:w-auto lg:flex-grow">Registros de Consumo de Gas</h2>
        <form id="gas-filter-form" action="{{ route('formulario.gasConsumption.records') }}" method="GET" class="**mt-4 lg:mt-0**">
            <div class="flex items-center gap-2">
                <input type="date" id="filter_date" name="filter_date" value="{{ $filterDate ?? \Carbon\Carbon::now()->format('Y-m-d') }}"
                    class="shadow appearance-none border border-light-gray rounded py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
                <button type="submit" class="bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded">
                    Filtrar
                </button>
            </div>
        </form>
    </div>    
    @if(empty($rs_gasConsumption))
        <p class="text-gray-600">No hay registros de consumo de gas para mostrar.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray- text-center">
                <thead>
                    <tr>
                        @foreach($displayHeaders as $colIndex => $header) {{-- Este bucle solo mostrará los headers que quedan --}}
                            <th class="py-2 px-4 border-b">{{ $header }}</th>
                        @endforeach
                        <th class="py-2 px-4 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rs_gasConsumption as $i => $record)
                        <tr id="row-gas-{{ $record['gasconsumption_id'] }}" class="{{ ($record['gasconsumption_status'] === 0) ? 'bg-gray-700 text-white' : '' }}">
                            @foreach($displayHeaders as $colIndex => $headerName) {{-- Este bucle accederá a los datos por el índice original --}}
                                @switch($colIndex)
                                    @case('gasconsumption_date')
                                        <td class="py-2 px-4 border-b">{{date('d-m-Y', strtotime($record[$colIndex]))}}</td>
                                        @break
                                    @case('gasconsumption_status')
                                        <td class="py-2 px-4 border-b">{{ ($record[$colIndex] === 1) ? 'Activo' : 'Inactivo' }}</td>
                                        @break                                        
                                    @default
                                        <td class="py-2 px-4 border-b">{{ $record[$colIndex] ?? '' }}</td>                                    
                                @endswitch    
                            @endforeach
                            <td class="py-2 px-4 border-b">
                                @if ($record['gasconsumption_status'] === 1)
                                    <button class="delete-gas-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                            data-row-number="{{ $record['gasconsumption_id'] }}">
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