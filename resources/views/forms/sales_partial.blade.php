{{-- resources/views/forms/sales_partial.blade.php --}}

<div id="form-messages" class="mb-4"></div>

<form id="salesForm" action="{{ route('formulario.sales.submit') }}" method="POST" class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray" autocomplete="off">
    @csrf

    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Ingresar Datos de Ventas</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="form-group">
            <label for="fechaRegistro">Fecha del Registro:</label>
            <input type="date" id="fechaRegistro" name="fechaRegistro" value="{{ old('fechaRegistro') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-fechaRegistro" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoWeb">Venta Página Web:</label>
            <input type="text" id="montoWeb" name="montoWeb" value="{{ old('montoWeb') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoWeb" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoCallcenter">Venta Call Center:</label>
            <input type="text" id="montoCallcenter" name="montoCallcenter" value="{{ old('montoCallcenter') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoCallcenter" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoOTA">Venta OTA:</label>
            <input type="text" id="montoOTA" name="montoOTA" value="{{ old('montoOTA') }}" step="0.02" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoOTA" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoCorporativo">Venta Corporativa:</label>
            <input type="text" id="montoCorporativo" name="montoCorporativo" value="{{ old('montoCorporativo') }}" step="0.03" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoCorporativo" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoAgenciaNac">Venta Agencia Nac:</label>
            <input type="text" id="montoAgenciaNac" name="montoAgenciaNac" value="{{ old('montoAgenciaNac') }}" step="0.04" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoAgenciaNac" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoAgenciaInt">Venta Internacional:</label>
            <input type="text" id="montoAgenciaInt" name="montoAgenciaInt" value="{{ old('montoAgenciaInt') }}" step="0.05" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoAgenciaInt" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoArenas">Venta Arenas:</label>
            <input type="text" id="montoArenas" name="montoArenas" value="{{ old('montoArenas') }}" step="0.06" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoArenas" class="validation-error hidden"></p>
        </div>
    </div>

    <button type="submit" class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Guardar
    </button>
</form>