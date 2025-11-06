{{-- resources/views/forms/sales_records.blade.php --}}

<div class="bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold text-dark-navy mb-6">Registros de Ventas</h2>
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