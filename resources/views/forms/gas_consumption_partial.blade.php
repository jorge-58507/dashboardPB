{{-- resources/views/forms/gas_consumption_partial.blade.php --}}

<div id="form-messages" class="mb-4"></div>

{{-- AÑADE max-w-4xl mx-auto A LA ETIQUETA FORM --}}
<form id="gasConsumptionForm" action="{{ route('formulario.gas.submit') }}" method="POST"
      class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray">
    @csrf

    {{-- Contenedor del título para el formulario --}}
    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Ingrese los consumos de Gas Natural</h2>

    {{-- NUEVO CONTENEDOR GRID RESPONSIVO --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Cada div.form-group será un item en el grid --}}

        <div class="form-group">
            <label for="cala">Cala (Consumo):</label>
            <input type="number" id="cala" name="cala" value="{{ old('cala') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-cala" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="lavanderia">Lavanderia (Consumo):</label>
            <input type="number" id="lavanderia" name="lavanderia" value="{{ old('lavanderia') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-lavanderia" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="cocina">Cocina (Consumo):</label>
            <input type="number" id="cocina" name="cocina" value="{{ old('cocina') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-cocina" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="velero">Velero (Consumo):</label>
            <input type="number" id="velero" name="velero" value="{{ old('velero') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-velero" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="agua">Agua (Consumo):</label>
            <input type="number" id="agua" name="agua" value="{{ old('agua') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-agua" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="date">Fecha:</label>
            <input type="date" id="date" name="date" value="{{ old('date') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-date" class="validation-error hidden"></p>
        </div>

    </div> {{-- CIERRE DEL CONTENEDOR GRID --}}

    <button type="submit" class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Enviar Datos
    </button>
    &nbsp;
    <button type="button" class="block mx-auto w-fit bg-green-300 text-green-900 py-2 px-4 rounded-lg shadow-md hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75">
        Ver Registros
    </button>

</form>