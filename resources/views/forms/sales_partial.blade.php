{{-- resources/views/forms/sales_partial.blade.php --}}

<div id="form-messages" class="mb-4"></div>

<form id="salesForm" action="{{ route('formulario.sales.submit') }}" method="POST" class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md border border-light-gray" autocomplete="off">
    @csrf

    <h2 class="text-2xl font-bold mb-6 text-center text-accent-blue">Ingresar Datos de Ventas</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="form-group">
            <label for="montoTotalHospedaje">Monto Total Ventas Hospedaje:</label>
            <input type="text" id="montoTotalHospedaje" name="montoTotalHospedaje" value="{{ old('montoTotalHospedaje') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-montoTotalHospedaje" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="cantidadClienteHospedado">Cantidad Clientes Hospedados:</label>
            <input type="text" id="cantidadClienteHospedado" name="cantidadClienteHospedado" value="{{ old('cantidadClienteHospedado') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-cantidadClienteHospedado" class="validation-error hidden"></p>
        </div>

        <div class="form-group">
            <label for="totalVentaOtroIngreso">Total Ventas Otros Ingresos:</label>
            <input type="text" id="totalVentaOtroIngreso" name="totalVentaOtroIngreso" value="{{ old('totalVentaOtroIngreso') }}" step="0.01" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-totalVentaOtroIngreso" class="validation-error hidden"></p>
        </div>
        
        <div class="form-group">
            <label for="fechaRegistro">Fecha del Registro:</label>
            <input type="date" id="fechaRegistro" name="fechaRegistro" value="{{ old('fechaRegistro') }}" required
                   class="shadow appearance-none border border-light-gray rounded w-full py-2 px-3 text-dark-navy leading-tight focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-accent-blue">
            <p id="error-fechaRegistro" class="validation-error hidden"></p>
        </div>
    </div>

    <button type="submit"
            class="block mx-auto w-fit bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Enviar Datos
    </button>
</form>