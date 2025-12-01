{{-- resources/views/forms/sales_partial.blade.php --}}

<form id="auditorForm" action="{{ route('formulario.auditor.submit') }}" method="POST" class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray" autocomplete="off">
    @csrf

    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Datos de Ingresos</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="form-group">
            <label for="fechaRegistro">Fecha del Registro:</label>
            <input type="date" id="fechaRegistro" name="fechaRegistro" value="{{ old('fechaRegistro') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-fechaRegistro" class="validation-error hidden"></p>
        </div>
        <div class="hidden md:block">&nbsp;</div>

        <div class="form-group **md:col-start-3**">
            <label for="montoAB">Ingresos por A&B:</label>
            <input type="text" id="montoAB" name="montoAB" value="{{ old('montoAB') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoAB" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="montoOtro">Otros Ingresos:</label>
            <input type="text" id="montoOtro" name="montoOtro" value="{{ old('montoOtro') }}" step="0.02" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoOtro" class="validation-error hidden"></p>
        </div>
    </div>

    <button type="submit" class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Guardar
    </button>
</form>