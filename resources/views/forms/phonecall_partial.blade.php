{{-- resources/views/forms/phonecall_partial.blade.php --}}
{{-- <div class="bg-white p-6 rounded-lg shadow-lg"> --}}
<div id="form-messages" class="mt-4 mb-4"></div>

<form id="phoneCallForm" action="{{ route('formulario.phonecall.submit') }}" method="POST" class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray" autocomplete="off">
    @csrf

    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Rendimiento Diario de Llamadas</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label for="fechaRegistro" class="block text-gray-700 text-sm font-bold mb-2">Fecha del Registro:</label>
            <input type="date" name="fechaRegistro" id="fechaRegistro"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required max="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
            <p id="error-fechaRegistro" class="text-red-500 text-xs italic hidden"></p>
        </div>

        <div>
            <label for="cantidadLlamadas" class="block text-gray-700 text-sm font-bold mb-2">Cantidad de Llamadas:</label>
            <input type="text" name="cantidadLlamadas" id="cantidadLlamadas" min="0" step="1"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Ej: 50" required>
            <p id="error-cantidadLlamadas" class="text-red-500 text-xs italic hidden"></p>
        </div>

        <div>
            <label for="ventasLogradas" class="block text-gray-700 text-sm font-bold mb-2">Ventas Logradas:</label>
            <input type="text" name="ventasLogradas" id="ventasLogradas" min="0" step="1"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Ej: 5" required>
            <p id="error-ventasLogradas" class="text-red-500 text-xs italic hidden"></p>
        </div>

        <div>
            <label for="tiempoPromedioLlamadas" class="block text-gray-700 text-sm font-bold mb-2">Tiempo Promedio (minutos):</label>
            <input type="text" name="tiempoPromedioLlamadas" id="tiempoPromedioLlamadas" min="0" step="0.01"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Ej: 2.5" required>
            <p id="error-tiempoPromedioLlamadas" class="text-red-500 text-xs italic hidden"></p>
        </div>
    </div>

    <button type="submit"
            class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Enviar Datos
    </button>
</form>
{{-- </div> --}}