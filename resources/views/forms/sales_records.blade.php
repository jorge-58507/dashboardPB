{{-- resources/views/forms/sales_records.blade.php --}}

<div class="bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold text-dark-navy mb-6">Registros de Ventas</h2>

    {{-- Contenedor para mensajes de éxito o error --}}
    <div id="records-messages" class="mt-4 mb-4"></div>

    @if(empty($records))
        <p class="text-gray-600">No hay registros de ventas para mostrar.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b"># Fila</th>
                        @foreach($displayHeaders as $header)
                            <th class="py-2 px-4 border-b">{{ $header }}</th>
                        @endforeach
                        <th class="py-2 px-4 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        {{-- El ID de la fila es crucial para la eliminación visual en JavaScript --}}
                        <tr id="row-sales-{{ $record['row_number_gs'] }}">
                            <td class="py-2 px-4 border-b">{{ $record['row_number_gs'] }}</td>
                            @foreach($displayHeaders as $colIndex => $headerName)
                                <td class="py-2 px-4 border-b">{{ $record['data_cols'][$colIndex] ?? '' }}</td>
                            @endforeach
                            <td class="py-2 px-4 border-b">
                                <button class="delete-sales-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                        data-row-number="{{ $record['row_number_gs'] }}">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>