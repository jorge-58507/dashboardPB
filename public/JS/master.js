var toastIt = function (message, type = "success") {
    const toastMessageElement = document.getElementById("toast-message");
    let toastTimeout; // Variable para almacenar el ID del temporizador del toast

    clearTimeout(toastTimeout); // Limpiar cualquier temporizador anterior

    // Resetear clases de color
    toastMessageElement.classList.remove("bg-emerald-500", "bg-red-500");

    // Aplicar color según el tipo
    if (type === "success") {
        toastMessageElement.classList.add("bg-emerald-500");
    } else if (type === "error") {
        toastMessageElement.classList.add("bg-red-500");
    } else {
        toastMessageElement.classList.add("bg-gray-700"); // Color por defecto
    }

    // Establecer el mensaje
    toastMessageElement.textContent = message;

    // Mostrar el toast con transición
    toastMessageElement.classList.remove("opacity-0", "translate-y-full");
    toastMessageElement.classList.add("opacity-100", "translate-y-0");

    // Ocultar el toast automáticamente después de 3 segundos
    toastTimeout = setTimeout(() => {
        toastMessageElement.classList.remove("opacity-100", "translate-y-0");
        toastMessageElement.classList.add("opacity-0", "translate-y-full");
    }, 3000); // 3 segundos
};

var initializeGasConsumptionForm = function () {
    const form = document.getElementById("gasConsumptionForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada

    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    enforceNumericInput("cala", true);
    enforceNumericInput("lavanderia", true);
    enforceNumericInput("cocina", true);
    enforceNumericInput("velero", true);
    enforceNumericInput("agua", true);

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        document.querySelectorAll(".validation-error").forEach((el) => {
            el.textContent = "";
            el.classList.add("hidden");
        });

        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());
        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";
        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": formCsrfToken,
                },
                body: JSON.stringify(data),
            });
            const result = await response.json();
            if (result.status === "confirm") {
                const res = await Swal.fire({
                    title: "Deseas actualizar el registro?",
                    text: "No podrás revertir esta acción",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Sí, eliminar",
                    cancelButtonText: "Cancelar",
                });
                if (res.isConfirmed) {
                    const responseData = result.data;
                    try {
                        const response = await fetch(newForm.action, {
                            method: "PUT",
                            headers: {
                                "Content-Type": "application/json",
                                Accept: "application/json",
                                "X-CSRF-TOKEN": formCsrfToken,
                            },
                            body: JSON.stringify(responseData),
                        });
                        const ans = await response.json();
                        if (ans.status === "success") {
                            newForm.reset();
                            toastIt(ans.message, "success");
                        } else {
                            const errorMessage =    ans.message ||   "Error al actualizar el registro.";
                            toastIt(errorMessage, "error");
                        }
                    } catch (error) {
                        console.error("Error al enviar la solicitud de eliminación:", error);
                        toastIt("Error de conexión al eliminar gas: " + error.message, "error");
                    }
                }
            }else{
                newForm.reset();
                toastIt(result.message, "success");
            }
        } catch (error) {
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message, "error");
        } finally {
            // Volvemos a habilitar el botón y restauramos su texto
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializeGasConsumptionRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros no encontrado. No se puede inicializar la eliminación de gas."
        );
        return;
    }

    recordsContainer.addEventListener("click", async function (event) {
        if (event.target.classList.contains("delete-gas-record")) {
            const button = event.target;
            const gasConsumption_id = button.dataset.rowNumber;

            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });

            if (res.isConfirmed) {
                button.disabled = true;
                button.textContent = "Eliminando...";

                try {
                    const response = await fetch(
                        "/formulario/delete-gas-consumption",
                        {
                            method: "DELETE",
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                                Accept: "application/json",
                                "Content-Type": "application/json",
                            },
                            body: JSON.stringify({ gasConsumption_id }),
                        }
                    );

                    const result = await response.json();

                    if (response.ok) {
                        toastIt(result.message, "success");
                        const rowElement = document.getElementById(`row-gas-${gasConsumption_id}`);
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        const errorMessage = result.message || "Error al eliminar el registro de gas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error("Error al enviar la solicitud de eliminación:",error);
                    toastIt("Error de conexión al eliminar gas: " + error.message, "error");
                } finally {
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializeGasConsumptionRecordsFilter = function () {
    const form = document.getElementById("gas-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializeGasConsumptionRecordsDeletion();
            initializeGasConsumptionRecordsFilter(); // Para que el filtro siga funcionando
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};

var initializeSalesForm = function () {
    const form = document.getElementById("salesForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada
    
    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    // --- NUEVAS LLAMADAS PARA RESTRINGIR LA ENTRADA NUMÉRICA ---
    enforceNumericInput("montoWeb", true); // Permitir decimales
    enforceNumericInput("montoCallcenter", true); // Permitir decimales
    enforceNumericInput("montoOTA", true); // Permitir decimales
    enforceNumericInput("montoCorporativo", true); // Permitir decimales
    enforceNumericInput("montoAgenciaNac", true); // Permitir decimales
    enforceNumericInput("montoAgenciaInt", true); // Permitir decimales
    enforceNumericInput("montoArenas", true); // Permitir decimales
    // -------------------------------------------------------------

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";

        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());
        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": formCsrfToken,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();
            if (result.status === "confirm") {
                const res = await Swal.fire({
                    title: "Deseas actualizar el registro?",
                    text: "No podrás revertir esta acción",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Sí, actualizar",
                    cancelButtonText: "Cancelar",
                });

                if (res.isConfirmed) {
                    const responseData = result.data;
                    try {
                        const response = await fetch(newForm.action, {
                            method: "PUT",
                            headers: {
                                "Content-Type": "application/json",
                                Accept: "application/json",
                                "X-CSRF-TOKEN": formCsrfToken,
                            },
                            body: JSON.stringify(responseData),
                        });
                        const ans = await response.json();
                        if (ans.status === "success") {
                            newForm.reset();
                            toastIt(ans.message, "success");
                        } else {
                            const errorMessage =
                                ans.message ||
                                "Error al actualizar el registro.";
                            toastIt(errorMessage, "error");
                        }
                    } catch (error) {
                        console.error("Error al enviar la solicitud de eliminación:",error);
                        toastIt("Error de conexión al eliminar gas: " +error.message,"error");
                    }
                }
            } else {
                newForm.reset();
                toastIt(result.message, "success");
            }
        } catch (error) {
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message); // Usar toastIt directamente
        } finally {
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializeSalesRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn("Contenedor de registros no encontrado. No se puede inicializar la eliminación de ventas.");
        return;
    }
    recordsContainer.addEventListener("click", async function (event) {
        if (event.target.classList.contains("delete-sales-record")) {
            const button = event.target;
            const sale_id = button.dataset.rowNumber;

            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });
            if (res.isConfirmed) {
                button.disabled = true;
                button.textContent = "Eliminando...";

                try {
                    const response = await fetch("/formulario/delete-sales", {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ "sale_id" : sale_id }),
                    });
                    const result = await response.json();
                    if (response.ok) {
                        toastIt(result.message, "success");
                        // sombrear la fila visualmente, usando el ID específico de llamadas
                        const rowElement = document.getElementById(`row-sales-${sale_id}`);
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        const errorMessage = result.message || "Error al eliminar el registro de ventas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error("Error al enviar la solicitud de eliminación:",error);
                    toastIt("Error de conexión al eliminar ventas: " + error.message,"error");
                } finally {
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializeSalesRecordsFilter = function () {
    const form = document.getElementById("sale-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializeSalesRecordsDeletion();
            initializeSalesRecordsFilter(); // Para que el filtro siga funcionando
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};


var initializePhoneCallForm = function () {
    const form = document.getElementById("phoneCallForm");
    if (!form) {
        console.warn("Formulario 'phoneCallForm' no encontrado. No se puede inicializar.");
        return; // Si el formulario no está en el DOM, no hacer nada
    }
    const formCsrfToken = document.querySelector('#phoneCallForm input[name="_token"]').value;

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    // Esto crea un clon del formulario y lo reemplaza para eliminar cualquier listener previo
    const oldForm = form;
    const newForm = oldForm.cloneNode(true);
    oldForm.parentNode.replaceChild(newForm, oldForm);

    enforceNumericInput("cantidadLlamadas", false); // Solo enteros
    enforceNumericInput("ventasLogradas", false); // Solo enteros
    enforceNumericInput("tiempoPromedioLlamadas", true); // Permitir decimales
    // ---------------------------------------------------------------------

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";

        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": formCsrfToken,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();
            if (response.ok) {
                if (result.status === "confirm") {
                    const res = await Swal.fire({
                        title: "Deseas actualizar el registro?",
                        text: "No podrás revertir esta acción",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Sí, actualizar",
                        cancelButtonText: "Cancelar",
                    });

                    if (res.isConfirmed) {
                        const responseData = result.data;
                        try {
                            const response = await fetch(newForm.action, {
                                method: "PUT",
                                headers: {
                                    "Content-Type": "application/json",
                                    Accept: "application/json",
                                    "X-CSRF-TOKEN": formCsrfToken,
                                },
                                body: JSON.stringify(responseData),
                            });
                            const ans = await response.json();
                            if (ans.status === "success") {
                                newForm.reset();
                                toastIt(ans.message, "success");
                            } else {
                                const errorMessage = ans.message || "Error al actualizar el registro.";
                                toastIt(errorMessage, "error");
                            }
                        } catch (error) {
                            console.error("Error al enviar la solicitud de eliminación:",error);
                            toastIt("Error de conexión al eliminar gas: " +error.message,"error");
                        }
                    }
                } else {
                    newForm.reset();
                    toastIt(result.message, "success");
                }
            }else{
                let errorMessage = result.message || "Ocurrió un error inesperado.";
                if (response.status === 422 && result.errors) {
                    errorMessage = "Falló la validación. Por favor, revisa tus entradas.";
                    for (const field in result.errors) {
                        const errorElement = newForm.querySelector(
                            `#error-${field}`
                        );
                        if (errorElement) {
                            errorElement.textContent = result.errors[field][0];
                            errorElement.classList.remove("hidden");
                        }
                    }
                }
                toastIt(errorMessage); // Usar toastIt directamente
            }
        } catch (error) {
            console.error("Error al enviar el formulario de llamadas:", error);
            toastIt("Error de conexión al enviar el formulario de llamadas: " +error.message);
        } finally {
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializePhoneCallRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn("Contenedor de registros no encontrado. No se puede inicializar la eliminación de llamadas.");
        return;
    }
    recordsContainer.addEventListener("click", async function (event) {
        if (event.target.classList.contains("delete-phonecall-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber;

            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });
            if (res.isConfirmed) {
                button.disabled = true;
                button.textContent = "Eliminando...";

                try {
                    const response = await fetch("/formulario/delete-phonecall", {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ id: rowNumber }),
                    });

                    const result = await response.json();

                    if (response.ok) {
                        toastIt(result.message, "success");
                        // Eliminar la fila visualmente, usando el ID específico de llamadas
                        const rowElement = document.getElementById(`row-phonecall-${rowNumber}`);
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        let errorMessage = result.message || "Error al eliminar el registro de llamadas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error("Error al enviar la solicitud de eliminación de llamadas:",error);
                    toastIt("Error de conexión al eliminar llamadas: " + error.message,"error");
                } finally {
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializePhoneCallRecordsFilter = function () {
    const form = document.getElementById("PhoneCall-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializePhoneCallRecordsDeletion();
            initializePhoneCallRecordsFilter(); // Para que el filtro siga funcionando
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};


var initializeLaundryForm = function () {
    const form = document.getElementById("laundryForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada

    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    enforceNumericInput("totalGasto", true); // Permitir decimales
    enforceNumericInput("cantidadCiclos", false); // Solo enteros

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";

        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": formCsrfToken,
                    Accept: "application/json",
                    "Content-Type": "application/json", // Importante para enviar JSON
                },
                body: JSON.stringify(data), // Enviar los datos como JSON
            });

            const result = await response.json();

            if (response.ok) {
                if (result.status === "confirm") {
                    const res = await Swal.fire({
                        title: result.message,
                        text: "No podrás revertir esta acción",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Sí, actualizar",
                        cancelButtonText: "Cancelar",
                    });

                    if (res.isConfirmed) {
                        const responseData = result.data;
                        try {
                            const response = await fetch(newForm.action, {
                                method: "PUT",
                                headers: {
                                    "Content-Type": "application/json",
                                    Accept: "application/json",
                                    "X-CSRF-TOKEN": formCsrfToken,
                                },
                                body: JSON.stringify(responseData),
                            });
                            const ans = await response.json();
                            if (ans.status === "success") {
                                newForm.reset();
                                toastIt(ans.message, "success");
                            } else {
                                const errorMessage = ans.message || "Error al actualizar el registro.";
                                toastIt(errorMessage, "error");
                            }
                        } catch (error) {
                            console.error("Error al enviar la solicitud de eliminación:",error);
                            toastIt("Error de conexión al eliminar gas: " +error.message,"error");
                        }
                    }
                } else {
                    newForm.reset();
                    if (response.status > 299) {
                        toastIt(result.message, "error");
                    }else{
                        toastIt(result.message, "success");
                    }
                }
            } else {
                let errorMessage = result.message || "Ocurrió un error inesperado.";
                if (response.status === 422 && result.errors) {
                    errorMessage = "Falló la validación. Por favor, revisa tus entradas.";
                    for (const field in result.errors) {
                        const errorElement = newForm.querySelector(
                            `#error-${field}`
                        );
                        if (errorElement) {
                            errorElement.textContent = result.errors[field][0];
                            errorElement.classList.remove("hidden");
                        }
                    }
                }
                toastIt(errorMessage,"error"); // Usar toastIt directamente
            }
        } catch (error) {
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message, "error");
        } finally {
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializeLaundryRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn("Contenedor de registros no encontrado. No se puede inicializar la eliminación de lavandería.");
        return;
    }

    recordsContainer.addEventListener("click", async function (event) {
        // Buscamos la clase específica para Lavandería: 'delete-laundry-record'
        if (event.target.classList.contains("delete-laundry-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber;

            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });
            if (res.isConfirmed) {
                button.disabled = true;
                button.textContent = "Eliminando...";

                try {
                    const response = await fetch("/formulario/delete-laundry", {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ id: rowNumber }),
                    });

                    const result = await response.json();

                    if (response.ok) {
                        toastIt(result.message, "success");
                        // Eliminar la fila visualmente, usando el ID específico de llamadas
                        const rowElement = document.getElementById(`row-laundry-${rowNumber}`);
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        let errorMessage = result.message || "Error al eliminar el registro de llamadas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error("Error al enviar la solicitud de eliminación de llamadas:",error);
                    toastIt("Error de conexión al eliminar llamadas: " + error.message,"error");
                } finally {
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializeLaundryRecordsFilter = function () {
    const form = document.getElementById("laundry-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializeLaundryRecordsDeletion();
            initializeLaundryRecordsFilter();
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};


var initializeInventoryHkForm = function () {
    const form = document.getElementById("inventoryHkForm");
    if (!form) {
        console.warn("Formulario 'inventoryHkForm' no encontrado. No se puede inicializar.");
        return;
    }
    const formCsrfToken = document.querySelector('#inventoryHkForm input[name="_token"]').value;

    const oldForm = form;
    const newForm = oldForm.cloneNode(true);
    oldForm.parentNode.replaceChild(newForm, oldForm);

    enforceNumericInput("sheet_k", false);
    enforceNumericInput("sheet_q", false);
    enforceNumericInput("pillowcase_k", false);
    enforceNumericInput("pillowcase_q", false);
    enforceNumericInput("pillow_k", false);
    enforceNumericInput("pillow_q", false);
    enforceNumericInput("mattressprotector_k", false);
    enforceNumericInput("mattressprotector_q", false);
    enforceNumericInput("towel_blank", false);
    enforceNumericInput("hand_towel", false);
    enforceNumericInput("foot_towel", false);
    enforceNumericInput("face_towel", false);
    enforceNumericInput("towel_blue", false);
    enforceNumericInput("blanket_blue", false);
    enforceNumericInput("blanket_green", false);
    enforceNumericInput("duveth_k", false);
    enforceNumericInput("duveth_q", false);
    enforceNumericInput("cover_k", false);
    enforceNumericInput("cover_q", false);
    enforceNumericInput("bedskirt_k", false);
    enforceNumericInput("bedskirt_q", false);
    // ---------------------------------------------------------------------

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";
        
        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": formCsrfToken,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();

            if (response.ok) {
                if (result.status === "confirm") {
                    const res = await Swal.fire({
                        title: result.message,
                        text: "No podrás revertir esta acción",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Sí, actualizar",
                        cancelButtonText: "Cancelar",
                    });

                    if (res.isConfirmed) {
                        const responseData = result.data;
                        try {
                            const response = await fetch(newForm.action, {
                                method: "PUT",
                                headers: {
                                    "Content-Type": "application/json",
                                    Accept: "application/json",
                                    "X-CSRF-TOKEN": formCsrfToken,
                                },
                                body: JSON.stringify(responseData),
                            });
                            const ans = await response.json();
                            if (ans.status === "success") {
                                newForm.reset();
                                toastIt(ans.message, "success");
                            } else {
                                const errorMessage = ans.message || "Error al actualizar el registro.";
                                toastIt(errorMessage, "error");
                            }
                        } catch (error) {
                            console.error("Error al enviar la solicitud de eliminación:",error);
                            toastIt("Error de conexión al eliminar gas: " +error.message,"error");
                        }
                    }
                } else {
                    newForm.reset();
                    toastIt(result.message, "success");
                }
            } else {
                let errorMessage = result.message || "Ocurrió un error inesperado al registrar el Inventario HK.";
                if (response.status === 422 && result.errors) {
                    errorMessage = "Falló la validación del Inventario HK. Por favor, revisa tus entradas.";
                    for (const field in result.errors) {
                        const errorElement = newForm.querySelector(`#error-${field}`);
                        if (errorElement) {
                            errorElement.textContent = result.errors[field][0];
                            errorElement.classList.remove("hidden");
                        }
                    }
                }
                toastIt(errorMessage);
            }
        } catch (error) {
            console.error("Error al enviar el formulario de Inventario HK:",error);
            toastIt("Error de conexión al enviar el formulario de Inventario HK: " +error.message);
        } finally { 
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializeInventoryHkRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn("Contenedor de registros ('form-content-container') no encontrado. No se puede inicializar la eliminación.");
        return;
    }
    recordsContainer.addEventListener("click", async function (event) {
        if (event.target.classList.contains("delete-inventoryhk-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber; // Obtenemos el número de fila de su atributo data

            // Pedimos confirmación al usuario
            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });
            if (res.isConfirmed) {
                button.disabled = true;
                button.textContent = "Eliminando...";

                try {
                    const response = await fetch("/formulario/delete-inventoryhk", {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ id: rowNumber }),
                    });

                    const result = await response.json();

                    if (response.ok) {
                        toastIt(result.message, "success");
                        // Eliminar la fila visualmente, usando el ID específico de llamadas
                        const rowElement = document.getElementById(`row-inventoryhk-${rowNumber}`);
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        let errorMessage = result.message || "Error al eliminar el registro de llamadas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error("Error al enviar la solicitud de eliminación de llamadas:",error);
                    toastIt("Error de conexión al eliminar llamadas: " + error.message,"error");
                } finally {
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializeInventoryhkRecordsFilter = function () {
    const form = document.getElementById("inventoryhk-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializeInventoryHkRecordsDeletion();
            initializeInventoryhkRecordsFilter();
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};


var initializeAuditorForm = function () {
    const form = document.getElementById("auditorForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada
    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario
    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    enforceNumericInput("montoAB", true); // Permitir decimales
    enforceNumericInput("montoOtro", true); // Permitir decimales
    // -------------------------------------------------------------
    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

        const button = newForm.querySelector('button[type="submit"], input[type="submit"]');
        button.disabled = true;
        button.textContent = "Enviando información";

        const formData = new FormData(newForm);
        const data = Object.fromEntries(formData.entries());
        try {
            const response = await fetch(newForm.action, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": formCsrfToken,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();
            if (response.ok) {
                if (result.status === "confirm") {
                    const res = await Swal.fire({
                        title: result.message,
                        text: "No podrás revertir esta acción",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Sí, actualizar",
                        cancelButtonText: "Cancelar",
                    });
                    if (res.isConfirmed) {
                        const responseData = result.data;
                        try {
                            const response = await fetch(newForm.action, {
                                method: "PUT",
                                headers: {
                                    "Content-Type": "application/json",
                                    Accept: "application/json",
                                    "X-CSRF-TOKEN": formCsrfToken,
                                },
                                body: JSON.stringify(responseData),
                            });
                            const ans = await response.json();
                            if (ans.status === "success") {
                                newForm.reset();
                                toastIt(ans.message, "success");
                            } else {
                                const errorMessage = ans.message || "Error al actualizar el registro.";
                                toastIt(errorMessage, "error");
                            }
                        } catch (error) {
                            console.error("Error al enviar la solicitud de eliminación:",error);
                            toastIt("Error de conexión al eliminar gas: " +error.message,"error");
                        }
                    }
                } else {
                    newForm.reset();
                    toastIt(result.message, "success");
                }
            } else {
                let errorMessage = result.message || "Ocurrió un error inesperado.";
                if (response.status === 422 && result.errors) {
                    errorMessage = "Falló la validación. Por favor, revisa tus entradas.";
                    for (const field in result.errors) {
                        const errorElement = newForm.querySelector(`#error-${field}`);
                        if (errorElement) {
                            errorElement.textContent = result.errors[field][0];
                            errorElement.classList.remove("hidden");
                        }
                    }
                }
                toastIt(errorMessage); // Usar toastIt directamente
            }
        } catch (error) {
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message); // Usar toastIt directamente
        } finally { 
            button.disabled = false;
            button.textContent = "Enviar Datos";
        }
    });
};
var initializeAuditorRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn("Contenedor de registros ('form-content-container') no encontrado. No se puede inicializar la eliminación.");
        return;
    }
    recordsContainer.addEventListener("click", async function (event) {
        if (event.target.classList.contains("delete-income-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber; // Obtenemos el número de fila de su atributo data

            const res = await Swal.fire({
                title: "¿Estás seguro?",
                text: "No podrás revertir esta acción",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar",
            });
            if (res.isConfirmed) {
                button.disabled = true; // Deshabilitamos el botón para evitar múltiples clics
                button.textContent = "Eliminando..."; // Cambiamos el texto del botón
                try {
                    // Enviamos la solicitud DELETE a la ruta de eliminación
                    const response = await fetch("/formulario/delete-auditor", {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"), // Obtenemos el token CSRF
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ row_number: rowNumber }), // Enviamos el número de fila
                    });

                    const result = await response.json(); // Parseamos la respuesta JSON

                    if (response.ok) {
                        toastIt(result.message, "success");
                        // sombrear la fila visualmente, usando el ID específico de llamadas
                        const rowElement = document.getElementById(`row-income-${rowNumber}`);                        
                        if (rowElement) {
                            rowElement.className = 'bg-gray-700 text-white';
                            const totalCell = rowElement.cells.length;
                            const statusCell = rowElement.cells[totalCell - 2];
                            statusCell.textContent = 'Inactivo';
                            const actionCell = rowElement.cells[totalCell - 1];
                            actionCell.textContent = '';
                        }
                    } else {
                        let errorMessage = result.message || "Error al eliminar el registro.";
                        toastIt(errorMessage, "error"); // Mostramos un toast de error
                    }
                } catch (error) {
                    // Si hay un error de red o de JavaScript
                    console.error("Error al enviar la solicitud de eliminación:",error);
                    toastIt("Error de conexión al eliminar: " + error.message,"error");
                } finally {
                    // Volvemos a habilitar el botón y restauramos su texto
                    button.disabled = false;
                    button.textContent = "Eliminar";
                }
            }
        }
    });
};
var initializeAuditorRecordsFilter = function () {
    const form = document.getElementById("income-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-content-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            // Re-inicializar los listeners para la nueva tabla
            initializeAuditorRecordsDeletion();
            initializeAuditorRecordsFilter();
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};



function enforceNumericInput(elementId, allowDecimals = false) {
    const inputElement = document.getElementById(elementId);
    if (inputElement) {
        inputElement.addEventListener("input", function () {
            let value = this.value;

            if (allowDecimals) {
                // 1. Permite dígitos (0-9), un punto o una coma
                value = value.replace(/[^0-9.]/g, ""); // Ahora también permite la coma

                // 3. Asegurarse de que solo haya un punto decimal
                const parts = value.split(".");
                if (parts.length > 2) {
                    // Si hay más de un punto, solo el primero es válido.
                    // Concatena la primera parte, el punto y el resto de las partes sin puntos adicionales.
                    value = parts[0] + "." + parts.slice(1).join("");
                }
            } else {
                // Solo permite dígitos (para números enteros)
                value = value.replace(/[^0-9]/g, "");
            }
            this.value = value; // Actualiza el valor del campo
        });
    }
}

function disableButton(form) {
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = "Enviando información";
    setTimeout(() => {
        btn.disabled = false;
        btn.textContent = "Guardar";
    }, 10000);
    //return true;
}

async function deactivateUser(button){
    const userId = button.dataset.rowNumber;
    const res = await Swal.fire({
        title: "¿Estás seguro?",
        text: "No podrás revertir esta acción",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    });
    if (res.isConfirmed) {
        button.disabled = true; // Deshabilitamos el botón para evitar múltiples clics
        try {
            // Enviamos la solicitud DELETE a la ruta de eliminación
            const response = await fetch("/profile", {
                method: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"), // Obtenemos el token CSRF
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ userId: userId }), // Enviamos el número de fila
            });

            const result = await response.json(); // Parseamos la respuesta JSON

            if (response.ok) {
                toastIt(result.message, "success");
                // sombrear la fila visualmente, usando el ID específico de llamadas
                const rowElement = document.getElementById(`row-user-${userId}`);                        
                if (rowElement) {
                    rowElement.className = 'bg-gray-700 text-white';
                    const totalCell = rowElement.cells.length;
                    const statusCell = rowElement.cells[totalCell - 2];
                    statusCell.textContent = 'Inactivo';
                    const actionCell = rowElement.cells[totalCell - 1];
                    actionCell.textContent = '';
                }
            } else {
                let errorMessage = result.message || "Error al eliminar el registro.";
                toastIt(errorMessage, "error"); // Mostramos un toast de error
            }
        } catch (error) {
            // Si hay un error de red o de JavaScript
            console.error("Error al enviar la solicitud de eliminación:",error);
            toastIt("Error de conexión al eliminar: " + error.message,"error");
        } finally {
            // Volvemos a habilitar el botón y restauramos su texto
            button.disabled = false;
        }
    }
}

async function editUser(button) {    
    const userId = button.dataset.rowNumber;
    const userName = button.dataset.rowName;
    const userEmail = button.dataset.rowEmail;
    const isChecked = (button.dataset.rowStatus == 1) ? 'checked' : '';

    const { value: formValues } = await Swal.fire({
        title: 'Ingresa los datos del usuario',
        html:
            // Campo Nombre
            '<input id="swal-input-name" class="swal2-input border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" placeholder="Nombre" type="text" style="width: 80%;" value="'+userName+'">' +
            
            // 🚨 Campo Email agregado aquí
            '<input id="swal-input-email" class="swal2-input border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" placeholder="Email" type="email" style="width: 80%;" value="'+userEmail+'">' + 
            
            '<div style="width: 80%; margin: 10px auto; text-align: left;">' +
                '<input type="checkbox" id="swal-input-status" ' + isChecked + '>' +
                '<label for="swal-input-status" style="margin-left: 10px;">Usuario Activo</label>' +
            '</div>' +

            // Campo Contraseña
            '<input id="swal-input-password" class="swal2-input border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" placeholder="Contraseña" type="password" style="width: 80%;" value="">' +
            
            // Campo Confirmar Contraseña
            '<input id="swal-input-confirm" class="swal2-input border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" placeholder="Confirmar Contraseña" type="password" style="width: 80%;" value="">',

        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",

        // Validación y obtención de valores
        preConfirm: () => {
            const name = document.getElementById('swal-input-name').value;
            const email = document.getElementById('swal-input-email').value;
            const password = document.getElementById('swal-input-password').value;
            const confirm = document.getElementById('swal-input-confirm').value;
            const statusCheckbox = document.getElementById('swal-input-status');

            // Simple función de validación de Email (puedes hacerla más robusta)
            const isValidEmail = (email) => {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(String(email).toLowerCase());
            }

            // 1. Validar campos vacíos
            if (!name || !email) {
                Swal.showValidationMessage('¡Todos los campos son obligatorios!');
                return false;
            }
            
            // 2. Validar formato de Email
            if (!isValidEmail(email)) {
                Swal.showValidationMessage('¡El formato del Email no es válido!');
                return false;
            }
            
            // 3. Validar que las contraseñas coincidan
            if (password !== confirm) {
                Swal.showValidationMessage('Las contraseñas no coinciden.');
                return false;
            }
            
            // Si pasa la validación, devuelve un objeto con todos los valores
            return { 
                name: name,
                email: email, // 👈 Devolvemos el Email
                password: password,
                password_confirmation: confirm,
                status: (statusCheckbox.checked) ? 1 : 0
            };
        }
    });

    if (formValues) {
        button.disabled = true; // Deshabilitamos el botón para evitar múltiples clics
        try {
            const response = await fetch("/profile", {
                method: "PATCH",
                headers: {
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"), // Obtenemos el token CSRF
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ id: userId, name: formValues.name, email: formValues.email, password: formValues.password, password_confirmation: formValues.password_confirmation, user_status: formValues.status }), // Enviamos el número de fila
            });

            const result = await response.json(); // Parseamos la respuesta JSON
            if (response.ok) {
                const data = result.data;
                toastIt(result.message, "success");
                var rowElement = document.getElementById(`row-user-${data.id}`);                
                rowElement.cells[0] = data.name;
                rowElement.cells[1] = data.email;
                if(data.user_status == 1){
                    rowElement.className = ''
                    rowElement.cells[2].innerHTML = 'Activo';
                    rowElement.cells[3].innerHTML = `
                        <button class="delete-user-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                data-row-number="${data.id}" onclick="deactivateUser(this)">
                            &#10006;
                        </button>
                        <button class="edit-user-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                data-row-number="${data.id}" data-row-name="${data.name}" data-row-email="${data.email}" data-row-status="${data.user_status}" onclick="editUser(this)">
                            &#9999;
                        </button>
                    `;
                }else{
                    rowElement.className = 'bg-gray-700 text-white'
                    rowElement.cells[2].innerHTML = 'Inactivo';
                    rowElement.cells[3].innerHTML = `
                        <button class="edit-user-record bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs"
                                data-row-number="${data.id}" data-row-name="${data.name}" data-row-email="${data.email}" data-row-status="${data.user_status}" onclick="editUser(this)">
                            &#9999;
                        </button>
                    `;
                }
            } else {
                let errorMessage = result.message || "Error al eliminar el registro.";
                toastIt(errorMessage, "error"); // Mostramos un toast de error
            }
        } catch (error) {
            // Si hay un error de red o de JavaScript
            console.error("Error al enviar la solicitud de eliminación:",error);
            toastIt("Error de conexión al eliminar: " + error.message,"error");
        } finally {
            // Volvemos a habilitar el botón y restauramos su texto
            button.disabled = false;
        }
    }
}

var initializeUserFilter = function () {
    const form = document.getElementById("user-filter-form");
    if (!form) return;

    const formContainer = document.getElementById("form-userList-container");

    // Clonar para evitar listeners duplicados
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm;

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        const formData = new FormData(newForm);
        const params = new URLSearchParams(formData);
        const formUrl = `${newForm.action}?${params.toString()}`;

        formContainer.innerHTML = '<p class="text-center text-dark-navy">Cargando registros...</p>';

        try {
            const response = await fetch(formUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "text/html",
                },
            });
            const htmlContent = await response.text();
            formContainer.innerHTML = htmlContent;
            initializeUserFilter();
        } catch (error) {
            console.error("Error al filtrar registros:", error);
            toastIt("Error al cargar los registros: " + error.message, "error");
        }
    });
};