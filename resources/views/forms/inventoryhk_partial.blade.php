{{-- resources/views/forms/inventoryhk_partial.blade.php --}}

<div class="bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold text-dark-navy mb-6">Inventario de Housekeeping (HK)</h2>

    {{-- Contenedor para mensajes de éxito o error --}}
    <div id="form-messages" class="mt-4 mb-4"></div>

    <form id="inventoryHkForm" action="{{ route('formulario.inventoryhk.submit') }}" onsubmit="disableButton(this);" method="POST">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Fecha del Inventario --}}
            <div class="col-span-full">
                <label for="fechaInventario" class="block text-gray-700 text-sm font-bold mb-2">Fecha del Inventario:</label>
                <input type="date" name="fechaInventario" id="fechaInventario" value="{{ \Carbon\Carbon::now()->format('Y-m-d')  }}"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       required max="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                <p id="error-fechaInventario" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Sábanas --}}
            <div>
                <label for="sheet_k" class="block text-gray-700 text-sm font-bold mb-2">Sábana King:</label>
                <input type="number" name="sheet_k" id="sheet_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-sheet_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="sheet_q" class="block text-gray-700 text-sm font-bold mb-2">Sábana Queen:</label>
                <input type="number" name="sheet_q" id="sheet_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-sheet_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Fundas de Almohada --}}
            <div>
                <label for="pillowcase_k" class="block text-gray-700 text-sm font-bold mb-2">Funda Almohada King:</label>
                <input type="number" name="pillowcase_k" id="pillowcase_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-pillowcase_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="pillowcase_q" class="block text-gray-700 text-sm font-bold mb-2">Funda Almohada Queen:</label>
                <input type="number" name="pillowcase_q" id="pillowcase_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-pillowcase_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Almohadas --}}
            <div>
                <label for="pillow_k" class="block text-gray-700 text-sm font-bold mb-2">Almohada King:</label>
                <input type="number" name="pillow_k" id="pillow_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-pillow_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="pillow_q" class="block text-gray-700 text-sm font-bold mb-2">Almohada Queen:</label>
                <input type="number" name="pillow_q" id="pillow_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-pillow_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Protectores de Colchón --}}
            <div>
                <label for="mattressprotector_k" class="block text-gray-700 text-sm font-bold mb-2">Protector Colchón King:</label>
                <input type="number" name="mattressprotector_k" id="mattressprotector_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-mattressprotector_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="mattressprotector_q" class="block text-gray-700 text-sm font-bold mb-2">Protector Colchón Queen:</label>
                <input type="number" name="mattressprotector_q" id="mattressprotector_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-mattressprotector_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Toallas --}}
            <div>
                <label for="towel_blank" class="block text-gray-700 text-sm font-bold mb-2">Toalla de Baño (Blanca):</label>
                <input type="number" name="towel_blank" id="towel_blank" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-towel_blank" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="hand_towel" class="block text-gray-700 text-sm font-bold mb-2">Toalla de Mano:</label>
                <input type="number" name="hand_towel" id="hand_towel" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-hand_towel" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="foot_towel" class="block text-gray-700 text-sm font-bold mb-2">Toalla de Pies:</label>
                <input type="number" name="foot_towel" id="foot_towel" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-foot_towel" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="face_towel" class="block text-gray-700 text-sm font-bold mb-2">Toalla Facial:</label>
                <input type="number" name="face_towel" id="face_towel" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-face_towel" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="towel_blue" class="block text-gray-700 text-sm font-bold mb-2">Toalla Alberca:</label>
                <input type="number" name="towel_blue" id="towel_blue" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-towel_blue" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Mantas --}}
            <div>
                <label for="blanket_blue" class="block text-gray-700 text-sm font-bold mb-2">Frazada Azul:</label>
                <input type="number" name="blanket_blue" id="blanket_blue" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-blanket_blue" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="blanket_green" class="block text-gray-700 text-sm font-bold mb-2">Frazada Verde:</label>
                <input type="number" name="blanket_green" id="blanket_green" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-blanket_green" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Fundas Nórdicas (Duvet) --}}
            <div>
                <label for="duveth_k" class="block text-gray-700 text-sm font-bold mb-2">Duvet King:</label>
                <input type="number" name="duveth_k" id="duveth_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-duveth_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="duveth_q" class="block text-gray-700 text-sm font-bold mb-2">Duvet Queen:</label>
                <input type="number" name="duveth_q" id="duveth_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-duveth_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Cubre Colchón --}}
            <div>
                <label for="cover_k" class="block text-gray-700 text-sm font-bold mb-2">Cobertor King:</label>
                <input type="number" name="cover_k" id="cover_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-cover_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="cover_q" class="block text-gray-700 text-sm font-bold mb-2">Cobertor Queen:</label>
                <input type="number" name="cover_q" id="cover_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-cover_q" class="text-red-500 text-xs italic hidden"></p>
            </div>

            {{-- Faldón de Cama --}}
            <div>
                <label for="bedskirt_k" class="block text-gray-700 text-sm font-bold mb-2">Faldón King:</label>
                <input type="number" name="bedskirt_k" id="bedskirt_k" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-bedskirt_k" class="text-red-500 text-xs italic hidden"></p>
            </div>
            <div>
                <label for="bedskirt_q" class="block text-gray-700 text-sm font-bold mb-2">Faldón Queen:</label>
                <input type="number" name="bedskirt_q" id="bedskirt_q" min="0" step="1" value="0"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <p id="error-bedskirt_q" class="text-red-500 text-xs italic hidden"></p>
            </div>
        </div>

        <div class="flex items-center justify-end mt-6">
            <button type="submit"
                    class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Registrar Inventario
            </button>
        </div>
    </form>
</div>