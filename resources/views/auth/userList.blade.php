<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Listado de Usuario') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Seleccione el usuario que desea modificar.') }}
        </p>
    </header>

    {{-- resources/views/forms/inventory/auditor_records.blade.php --}}
    @php
        $rs_user = $user_list[1];
        $displayHeaders = $user_list[0];
    @endphp
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="flex flex-col lg:flex-row justify-between items-center mb-6">
            <form id="user-filter-form" action="/userList-filter" method="GET" onsubmit="event.preventDefault();" class="mt-4 lg:mt-0" autocomplete="off">
                <div class="flex items-center gap-2">
                    <input type="text" id="filter_user" name="filter_user" value=""
                        class="shadow appearance-none border border-light-gray rounded py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
                    <button type="submit" class="bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>
        @if(empty($rs_user))
            <p class="text-gray-600">No hay registros de otros ingresos para mostrar.</p>
        @else
            <div class="overflow-x-auto" style="height: 300px;">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr>
                            @foreach($displayHeaders as $header) {{-- Usamos los nuevos encabezados personalizados --}}
                                <th class="py-2 px-4 border-b">{{ $header }}</th>
                            @endforeach
                            <th class="py-2 px-4 border-b">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="form-userList-container">
                        @include('auth.partial.user_records')
{{-- 
                        @foreach($rs_user as $i => $record)
                            <tr id="row-user-{{ $record['id'] }}" class="{{ ($record['user_status'] === 0) ? 'bg-gray-700 text-white' : '' }}">
                                @foreach($displayHeaders as $colIndex => $headerName)
                                    @switch($colIndex)
                                        @case('created_at')
                                            <td class="py-2 px-4 border-b">{{date('d-m-Y', strtotime($record[$colIndex]))}}</td>
                                            @break
                                        @case('user_status')
                                            <td class="py-2 px-4 border-b">{{ ($record[$colIndex] === 1) ? 'Activo' : 'Inactivo' }}</td>
                                            @break                                        
                                        @default
                                            <td class="py-2 px-4 border-b">{{ $record[$colIndex] ?? '' }}</td>                                    
                                    @endswitch
                                @endforeach
                                <td class="py-2 px-4 border-b">
                                    @if ($record['user_status'] === 1)
                                        <button class="delete-user-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                                data-row-number="{{ $record['id'] }}" onclick="deactivateUser(this)">
                                            &#10006;
                                        </button>
                                    @endif
                                    <button class="edit-user-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                            data-row-number="{{ $record['id'] }}" data-row-name="{{ $record['name'] }}" data-row-email="{{ $record['email'] }}" data-row-status="{{ $record['user_status'] }}" onclick="editUser(this)">
                                        &#9999;
                                    </button>
                                </td>
                            </tr>
                        @endforeach --}}
                    </tbody>
                </table>
            </div>

        @endif
    </div>



</section>