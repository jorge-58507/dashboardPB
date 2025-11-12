{{-- resources/views/forms/sales_records.blade.php --}}

<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex flex-col lg:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-dark-navy w-full lg:w-auto lg:flex-grow">Registros de Consumo de Gas</h2>
        <form id="sale-filter-form" action="{{ route('formulario.sales.records') }}" method="GET" class="mt-4 lg:mt-0">
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
        <p class="text-gray-600">No hay registros de ventas para mostrar.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        @foreach($displayHeaders as $colIndex => $header)
                            <th class="py-2 px-4 border-b">{{ $header }}</th>
                        @endforeach
                        <th class="py-2 px-4 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr id="row-sales-{{ $record['sale_id'] }}" class="{{ ($record['sale_status'] === 0) ? 'bg-gray-700 text-white' : '' }}">
                            @foreach($displayHeaders as $colIndex => $headerName)
                                @switch($colIndex)
                                    @case('sale_date')
                                        <td class="py-2 px-4 border-b">{{date('d-m-Y', strtotime($record[$colIndex]))}}</td>
                                        @break
                                    @case('sale_status')
                                        <td class="py-2 px-4 border-b">{{ ($record[$colIndex] === 1) ? 'Activo' : 'Inactivo' }}</td>
                                        @break                                        
                                    @default
                                        <td class="py-2 px-4 border-b">{{ $record[$colIndex] ?? '' }}</td>                                    
                                @endswitch                                                
                            @endforeach
                            <td class="py-2 px-4 border-b">
                                @if ($record['sale_status'] === 1)
                                    <button class="delete-sales-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                            data-row-number="{{ $record['sale_id'] }}">
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