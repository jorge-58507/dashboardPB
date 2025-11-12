<x-app-layout>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{-- Mensaje de bienvenida, puedes quitarlo o personalizarlo --}}
                    <h2 class="text-xl font-semibold mb-4 text-dark-navy">Bienvenido, {{ Auth::user()->name }}!</h2>
                    <p class="mb-6 text-dark-navy">Selecciona una opción del menú:</p>

                    <nav class="mb-8 flex flex-wrap gap-4 justify-center">
                        @hasanyrole('Mantenimiento|Admin')
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.gas_consumption_partial') }}">
                                Consumo de Gas
                            </button>
                        @endhasanyrole
                        @hasanyrole('Mantenimiento|Admin')
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.gasConsumption.records') }}">
                                Ver Registros Gas
                            </button>
                        @endhasanyrole

                        {{-- Botón "Electricidad" --}}
                        {{--
                        @hasrole('Admin')
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.electricidad_partial') }}"> 
                                Electricidad
                            </button>
                        @endhasrole
                        --}}

                        {{-- Botón "Ventas" --}}
                        @hasanyrole('Ventas|Admin') {{-- Visible para Ventas y Admin --}}
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.sales_partial') }}"> 
                                Ventas
                            </button>
                        @endhasanyrole
                        @hasanyrole('Ventas|Admin') {{-- Asume que el rol Ventas puede ver estos registros --}}
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.sales.records') }}">
                                Ver Registros Ventas
                            </button>
                        @endhasanyrole




                        {{-- Botón "Llamadas" --}}
                        @hasanyrole('Llamadas|Ventas|Admin') {{-- Visible para Llamadas y Admin --}}
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.phonecall_partial') }}"> {{-- ¡Ruta corregida aquí! --}}
                                Llamadas
                            </button>
                        @endhasanyrole
                        @hasanyrole('Recepcion|Admin') {{-- Asume que el rol Recepción puede ver estos registros --}}
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.phonecall.records') }}">
                                Ver Registros Llamadas
                            </button>
                        @endhasanyrole


                        {{-- Botón "Lavandería" --}}
                        @hasanyrole('Lavanderia|Housekeeping||Admin') {{-- Visible para Lavanderia, Housekeeping y Admin --}}
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.laundry_partial') }}">{{-- Esta ruta la crearemos luego --}}
                                Lavandería
                            </button>
                        @endhasanyrole
                        @hasanyrole('Lavanderia|Housekeeping|Admin') {{-- Asume que el rol Lavanderia puede ver estos registros --}}
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.laundry.records') }}">
                                Ver Registros Lavandería
                            </button>
                        @endhasanyrole


                        {{-- Botón "Inventario HK" --}}
                        @hasanyrole('Housekeeping|Admin') {{-- Visible para Housekeeping y Admin --}}
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.inventoryhk_partial') }}">
                                Inventario HK
                            </button>
                        @endhasanyrole
                        {{-- Botón "Ver Registros HK" --}}
                        @hasanyrole('Housekeeping|Admin') {{-- Ajusta los roles según quién deba ver este listado --}}
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.inventoryhk.records') }}">
                                Ver Registros HK
                            </button>
                        @endhasanyrole

                        {{-- Botón "Inventario A&B" --}}
                        {{--
                          @hasanyrole('A&B|Admin') 
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.inventario_ab_partial') }}">
                                Inventario A&B
                            </button>
                        @endhasanyrole
                        --}}

                        {{-- Botón "Inventario Auditor" --}}
                        @hasanyrole('Auditor|Admin') 
                            <button type="button" class="form-button bg-accent-blue hover:bg-dark-navy text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.auditor_partial') }}">
                                Ingresos
                            </button>
                        @endhasanyrole
                        {{-- Botón "Ver Registros Auditor" --}}
                        @hasanyrole('Auditor|Admin') {{-- Ajusta los roles según quién deba ver este listado --}}
                            <button type="button" class="form-button bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    data-form-url="{{ route('formulario.auditor.records') }}">
                                Ver Registros Ingresos
                            </button>
                        @endhasanyrole
                    </nav>

                    {{-- CONTENEDOR PARA CARGAR FORMULARIOS VÍA AJAX --}}
                    <div id="form-content-container" class="mt-8 p-6 bg-white rounded-lg shadow-xl mx-auto w-full max-w-7xl">
                        <p class="text-gray-600 text-center">Selecciona una opción para cargar el formulario o ver los registros.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Create a global object to hold our app-specific variables
        window.App = window.App || {}; // Ensures window.App exists
        // window.App.routes = {
        //     inventoryhkDelete: "{{ route('formulario.inventoryhk.delete') }}",
        // };
        window.App.csrfToken = "{{ csrf_token() }}";
    </script>
    <script src="/JS/master.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formButtons = document.querySelectorAll('.form-button');
            const formContainer = document.getElementById('form-content-container');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const formMessages = document.getElementById('form-messages');

            // Lógica de Manejo de Carga de Formularios
            formButtons.forEach(button => {
                button.addEventListener('click', async function() {                    
                    const formUrl = this.dataset.formUrl;
                    
                    formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando formulario...</p>';

                    try {
                        const response = await fetch(formUrl, {
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'text/html'
                            }
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        const htmlContent = await response.text();
                        formContainer.innerHTML = htmlContent;

                        // Inicializar el formulario correcto después de que se carga el HTML
                        if (formUrl === "{{ route('formulario.gas_consumption_partial') }}") {
                            initializeGasConsumptionForm();
                        } else if (formUrl === "{{ route('formulario.sales_partial') }}") {
                            initializeSalesForm();
                        } else if (formUrl === "{{ route('formulario.phonecall_partial') }}") {
                            initializePhoneCallForm();
                        } else if (formUrl === "{{ route('formulario.laundry_partial') }}") {
                            initializeLaundryForm();
                        } else if (formUrl === "{{ route('formulario.inventoryhk_partial') }}") { 
                            initializeInventoryHkForm();
                        } else if (formUrl === "{{ route('formulario.auditor_partial') }}") {
                            initializeAuditorForm();
                            
                        } else if (formUrl === "{{ route('formulario.gasConsumption.records') }}") { 
                            initializeGasConsumptionRecordsFilter();
                            initializeGasConsumptionRecordsDeletion();
                        }else if (formUrl === "{{ route('formulario.sales.records') }}") {
                            initializeSalesRecordsFilter();
                            initializeSalesRecordsDeletion();
                        }else if (formUrl === "{{ route('formulario.inventoryhk.records') }}") {
                            initializeInventoryHkRecordsDeletion();
                        }else if (formUrl === "{{ route('formulario.laundry.records') }}") {
                            initializeLaundryRecordsDeletion();
                        }else if (formUrl === "{{ route('formulario.phonecall.records') }}") {
                            initializePhoneCallRecordsDeletion();
                        }else if (formUrl === "{{ route('formulario.auditor.records') }}") {
                            initializeAuditorRecordsDeletion();
                        }
                    } catch (error) {
                        console.error('Error loading form:', error);
                        formContainer.innerHTML = `
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                                <p class="font-bold">Error al cargar el formulario.</p>
                                <p>${error.message}</p>
                            </div>
                        `;
                        // Usar toastIt directamente
                        toastIt('Error al cargar el formulario: ' + error.message);
                    }
                    
                });
            });
           
        });
    </script>
</x-app-layout>