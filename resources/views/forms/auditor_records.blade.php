{{-- resources/views/forms/inventory/auditor_records.blade.php --}}

<div class="bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold text-dark-navy mb-6">Registros de Inventario de Otros Ingresos</h2>

    {{-- Contenedor para mensajes de éxito o error de la tabla --}}
    <div id="records-messages" class="mt-4 mb-4"></div>

    @if(empty($records))
        <p class="text-gray-600">No hay registros de otros ingresos para mostrar.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b"># Fila</th> {{-- Columna para el número de fila de Google Sheets --}}
                        @foreach($displayHeaders as $header) {{-- Usamos los nuevos encabezados personalizados --}}
                            <th class="py-2 px-4 border-b">{{ $header }}</th>
                        @endforeach
                        <th class="py-2 px-4 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr id="row-{{ $record['row_number_gs'] }}">
                            <td class="py-2 px-4 border-b">{{ $record['row_number_gs'] }}</td>
                            @foreach($displayHeaders as $colIndex => $headerName) {{-- Iteramos por el índice del encabezado --}}
                                <td class="py-2 px-4 border-b">{{ $record['data_cols'][$colIndex] ?? '' }}</td>
                            @endforeach
                            <td class="py-2 px-4 border-b">
                                <button class="delete-auditor-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
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