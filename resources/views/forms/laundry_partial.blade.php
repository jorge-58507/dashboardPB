{{-- resources/views/forms/laundry_partial.blade.php --}}

<div id="form-messages" class="mb-4"></div>

<form id="laundryForm" action="{{ route('formulario.laundry.submit') }}" method="POST"
      class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray">
    @csrf

    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Ingrese los datos</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="form-group">
            <label for="fechaInicio">Fecha Inicio:</label>
            <input type="date" id="fechaInicio" name="fechaInicio" value="{{ old('fechaInicio') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-fechaInicio" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="fechaFin">Fecha Cierre:</label>
            <input type="date" id="fechaFin" name="fechaFin" value="{{ old('fechaFin') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-fechaFin" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="totalGasto">Total Gastado:</label>
            <input type="text" id="totalGasto" name="totalGasto" value="{{ old('totalGasto') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-totalGasto" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="cantidadCiclos">Cantidad de Ciclos:</label>
            <input type="text" id="cantidadCiclos" name="cantidadCiclos" value="{{ old('cantidadCiclos') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-cantidadCiclos" class="validation-error hidden"></p>
        </div>
    </div>

    <button type="submit"
            class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Enviar Datos
    </button>
</form>