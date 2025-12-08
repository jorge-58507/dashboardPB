@foreach($rs_user as $i => $record)
    <tr id="row-user-{{ $record['id'] }}" class="{{ ($record['user_status'] === 0) ? 'bg-gray-700 text-white' : '' }}">
        @foreach($displayHeaders as $colIndex => $headerName)
            @switch($colIndex)
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
@endforeach