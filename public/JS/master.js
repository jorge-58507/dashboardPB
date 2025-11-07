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

                        const rowElement = document.getElementById(
                            `row-gas-${gasConsumption_id}`
                        );
                        if (rowElement) {
                            rowElement.remove();
                        }
                    } else {
                        const errorMessage =
                            result.message ||
                            "Error al eliminar el registro de gas.";
                        toastIt(errorMessage, "error");
                    }
                } catch (error) {
                    console.error(
                        "Error al enviar la solicitud de eliminación:",
                        error
                    );
                    toastIt(
                        "Error de conexión al eliminar gas: " + error.message,
                        "error"
                    );
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
            if (response.ok) {
                newForm.reset();
                toastIt(result.message); // Usar toastIt directamente
            } else {
                let errorMessage =
                    result.message || "Ocurrió un error inesperado.";

                if (response.status === 422 && result.errors) {
                    errorMessage =
                        "Falló la validación. Por favor, revisa tus entradas.";
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
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message); // Usar toastIt directamente
        }
    });
};
var initializeSalesRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros no encontrado. No se puede inicializar la eliminación de ventas."
        );
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
                        const rowElement = document.getElementById(`row-sales-${sale_id}`);
                        console.log(rowElement);
                        
                        if (rowElement) {rowElement.remove();}
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


var initializeLaundryForm = function () {
    const form = document.getElementById("laundryForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada

    const formMessages = document.getElementById("form-messages");
    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    enforceNumericInput("totalGasto", true); // Permitir decimales
    enforceNumericInput("cantidadCiclos", false); // Solo enteros

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        formMessages.innerHTML = ""; // Limpiar mensajes anteriores
        // Ocultar todos los mensajes de validación
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

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
                newForm.reset(); // Limpiar el formulario
                toastIt(result.message, "success");
            } else {
                let errorMessage =
                    result.message || "Ocurrió un error inesperado.";

                if (response.status === 422 && result.errors) {
                    errorMessage =
                        "Falló la validación. Por favor, revisa tus entradas.";
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

                formMessages.innerHTML = `
                            <div class="error-message">
                                <p class="font-bold">${errorMessage}</p>
                            </div>
                        `;
                toastIt(errorMessage, "error");
            }
        } catch (error) {
            console.error("Error:", error);
            formMessages.innerHTML = `
                        <div class="error-message">
                            <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                            <p>${error.message}</p>
                        </div>
                    `;
            toastIt("Error de conexión: " + error.message, "error");
        }
    });
};
var initializeLaundryRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros no encontrado. No se puede inicializar la eliminación de lavandería."
        );
        return;
    }

    const formMessages = document.getElementById("records-messages");

    recordsContainer.addEventListener("click", async function (event) {
        // Buscamos la clase específica para Lavandería: 'delete-laundry-record'
        if (event.target.classList.contains("delete-laundry-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber;

            if (
                !confirm(
                    `¿Estás seguro de que quieres eliminar el registro de lavandería de la fila ${rowNumber}? Esta acción es irreversible.`
                )
            ) {
                return;
            }

            formMessages.innerHTML = "";
            button.disabled = true;
            button.textContent = "Eliminando...";

            try {
                // Usamos la URL directa para la ruta de eliminación de lavandería
                const response = await fetch("/formulario/delete-laundry", {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                        Accept: "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ row_number: rowNumber }),
                });

                const result = await response.json();

                if (response.ok) {
                    toastIt(result.message, "success");
                    // Eliminar la fila visualmente, usando el ID específico de lavandería
                    const rowElement = document.getElementById(
                        `row-laundry-${rowNumber}`
                    );
                    if (rowElement) {
                        rowElement.remove();
                    }
                    formMessages.innerHTML = `
                        <div class="success-message">
                            ${result.message}
                        </div>
                    `;
                } else {
                    let errorMessage =
                        result.message ||
                        "Error al eliminar el registro de lavandería.";
                    toastIt(errorMessage, "error");
                    formMessages.innerHTML = `
                        <div class="error-message">
                            <p class="font-bold">${errorMessage}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error(
                    "Error al enviar la solicitud de eliminación de lavandería:",
                    error
                );
                toastIt(
                    "Error de conexión al eliminar lavandería: " +
                        error.message,
                    "error"
                );
                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                button.disabled = false;
                button.textContent = "Eliminar";
            }
        }
    });
};


var initializePhoneCallForm = function () {
    const form = document.getElementById("phoneCallForm");
    if (!form) {
        console.warn(
            "Formulario 'phoneCallForm' no encontrado. No se puede inicializar."
        );
        return; // Si el formulario no está en el DOM, no hacer nada
    }

    const formMessages = document.getElementById("form-messages");
    const formCsrfToken = document.querySelector(
        '#phoneCallForm input[name="_token"]'
    ).value;

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    // Esto crea un clon del formulario y lo reemplaza para eliminar cualquier listener previo
    const oldForm = form;
    const newForm = oldForm.cloneNode(true);
    oldForm.parentNode.replaceChild(newForm, oldForm);

    // --- LLAMADAS PARA RESTRINGIR LA ENTRADA NUMÉRICA EN ESTE FORMULARIO ---
    // Los IDs de los campos deben coincidir con los de phonecall_partial.blade.php
    enforceNumericInput("cantidadLlamadas", false); // Solo enteros
    enforceNumericInput("ventasLogradas", false); // Solo enteros
    enforceNumericInput("tiempoPromedioLlamadas", true); // Permitir decimales
    // ---------------------------------------------------------------------

    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        formMessages.innerHTML = "";
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

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
                formMessages.innerHTML = `
                    <div class="success-message">
                        ${result.message}
                    </div>
                `;
                newForm.reset();
                toastIt(result.message); // Usar toastIt directamente
            } else {
                let errorMessage =
                    result.message || "Ocurrió un error inesperado.";

                if (response.status === 422 && result.errors) {
                    errorMessage =
                        "Falló la validación. Por favor, revisa tus entradas.";
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

                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">${errorMessage}</p>
                    </div>
                `;
                toastIt(errorMessage); // Usar toastIt directamente
            }
        } catch (error) {
            console.error("Error al enviar el formulario de llamadas:", error);
            formMessages.innerHTML = `
                <div class="error-message">
                    <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                    <p>${error.message}</p>
                </div>
            `;
            toastIt(
                "Error de conexión al enviar el formulario de llamadas: " +
                    error.message
            );
        }
    });
};
var initializePhoneCallRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros no encontrado. No se puede inicializar la eliminación de llamadas."
        );
        return;
    }

    const formMessages = document.getElementById("records-messages");

    recordsContainer.addEventListener("click", async function (event) {
        // Buscamos la clase específica para Llamadas: 'delete-phonecall-record'
        if (event.target.classList.contains("delete-phonecall-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber;

            if (
                !confirm(
                    `¿Estás seguro de que quieres eliminar el registro de llamadas de la fila ${rowNumber}? Esta acción es irreversible.`
                )
            ) {
                return;
            }

            formMessages.innerHTML = "";
            button.disabled = true;
            button.textContent = "Eliminando...";

            try {
                // Usamos la URL directa para la ruta de eliminación de llamadas
                const response = await fetch("/formulario/delete-phonecall", {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                        Accept: "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ row_number: rowNumber }),
                });

                const result = await response.json();

                if (response.ok) {
                    toastIt(result.message, "success");
                    // Eliminar la fila visualmente, usando el ID específico de llamadas
                    const rowElement = document.getElementById(
                        `row-phonecall-${rowNumber}`
                    );
                    if (rowElement) {
                        rowElement.remove();
                    }
                    formMessages.innerHTML = `
                        <div class="success-message">
                            ${result.message}
                        </div>
                    `;
                } else {
                    let errorMessage =
                        result.message ||
                        "Error al eliminar el registro de llamadas.";
                    toastIt(errorMessage, "error");
                    formMessages.innerHTML = `
                        <div class="error-message">
                            <p class="font-bold">${errorMessage}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error(
                    "Error al enviar la solicitud de eliminación de llamadas:",
                    error
                );
                toastIt(
                    "Error de conexión al eliminar llamadas: " + error.message,
                    "error"
                );
                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                button.disabled = false;
                button.textContent = "Eliminar";
            }
        }
    });
};

var initializeInventoryHkForm = function () {
    const form = document.getElementById("inventoryHkForm");
    if (!form) {
        console.warn(
            "Formulario 'inventoryHkForm' no encontrado. No se puede inicializar."
        );
        return;
    }
    console.log("Inicializando formulario de Inventario HK..."); // Debugging

    const formMessages = document.getElementById("form-messages");
    const formCsrfToken = document.querySelector(
        '#inventoryHkForm input[name="_token"]'
    ).value;

    // Limpiar listeners viejos
    const oldForm = form;
    const newForm = oldForm.cloneNode(true);
    oldForm.parentNode.replaceChild(newForm, oldForm);

    // --- LLAMADAS PARA RESTRINGIR LA ENTRADA NUMÉRICA EN ESTE FORMULARIO ---
    // Todos los campos de cantidad son enteros, por lo que 'allowDecimals' es 'false'
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
        formMessages.innerHTML = "";
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

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
                formMessages.innerHTML = `
                    <div class="success-message">
                        ${result.message}
                    </div>
                `;
                newForm.reset();
                toastIt(result.message);
            } else {
                let errorMessage =
                    result.message ||
                    "Ocurrió un error inesperado al registrar el Inventario HK.";

                if (response.status === 422 && result.errors) {
                    errorMessage =
                        "Falló la validación del Inventario HK. Por favor, revisa tus entradas.";
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

                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">${errorMessage}</p>
                    </div>
                `;
                toastIt(errorMessage);
            }
        } catch (error) {
            console.error(
                "Error al enviar el formulario de Inventario HK:",
                error
            );
            formMessages.innerHTML = `
                <div class="error-message">
                    <p class="font-bold">Ocurrió un error al conectar con el servidor para el Inventario HK.</p>
                    <p>${error.message}</p>
                </div>
            `;
            toastIt(
                "Error de conexión al enviar el formulario de Inventario HK: " +
                    error.message
            );
        }
    });
};
var initializeInventoryHkRecordsDeletion = function () {
    // El 'form-content-container' es donde se carga dinámicamente la tabla de registros.
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros ('form-content-container') no encontrado. No se puede inicializar la eliminación."
        );
        return;
    }

    const formMessages = document.getElementById("records-messages"); // Contenedor para mensajes específicos de la tabla

    // Usamos delegación de eventos porque los botones 'delete-hk-record' se cargan dinámicamente
    // después de que la página inicial ha cargado.
    recordsContainer.addEventListener("click", async function (event) {
        // Verificamos si el clic fue en un botón con la clase 'delete-hk-record'
        if (event.target.classList.contains("delete-hk-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber; // Obtenemos el número de fila de su atributo data

            // Pedimos confirmación al usuario
            if (
                !confirm(
                    `¿Estás seguro de que quieres eliminar el registro de la fila ${rowNumber}? Esta acción es irreversible.`
                )
            ) {
                return; // Si el usuario cancela, no hacemos nada
            }

            formMessages.innerHTML = ""; // Limpiamos mensajes previos
            button.disabled = true; // Deshabilitamos el botón para evitar múltiples clics
            button.textContent = "Eliminando..."; // Cambiamos el texto del botón

            try {
                // Enviamos la solicitud DELETE a la ruta de eliminación
                const response = await fetch("/formulario/delete-inventoryhk", {
                    // const response = await fetch("{{ route('formulario.inventoryhk.delete') }}", {
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
                    // Si la respuesta es exitosa (código 2xx)
                    toastIt(result.message, "success"); // Mostramos un toast de éxito
                    // Eliminamos la fila de la tabla visualmente para reflejar el cambio
                    const rowElement = document.getElementById(
                        `row-${rowNumber}`
                    );
                    if (rowElement) {
                        rowElement.remove();
                    }
                    // Mostramos un mensaje de éxito en el contenedor de mensajes de la tabla
                    formMessages.innerHTML = `
                        <div class="success-message">
                            ${result.message}
                        </div>
                    `;
                } else {
                    // Si hubo un error en la respuesta
                    let errorMessage =
                        result.message || "Error al eliminar el registro.";
                    toastIt(errorMessage, "error"); // Mostramos un toast de error
                    // Mostramos un mensaje de error en el contenedor de mensajes de la tabla
                    formMessages.innerHTML = `
                        <div class="error-message">
                            <p class="font-bold">${errorMessage}</p>
                        </div>
                    `;
                }
            } catch (error) {
                // Si hay un error de red o de JavaScript
                console.error(
                    "Error al enviar la solicitud de eliminación:",
                    error
                );
                toastIt(
                    "Error de conexión al eliminar: " + error.message,
                    "error"
                );
                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                // Volvemos a habilitar el botón y restauramos su texto
                button.disabled = false;
                button.textContent = "Eliminar";
            }
        }
    });
};

var initializeAuditorForm = function () {
    const form = document.getElementById("auditorForm");
    if (!form) return; // Si el formulario no está en el DOM, no hacer nada

    const formMessages = document.getElementById("form-messages");
    const formCsrfToken = document.querySelector('input[name="_token"]').value; // El token está en el input oculto del formulario

    // Limpiar listeners viejos si el formulario ya existía (útil si se carga varias veces)
    const oldForm = form.cloneNode(true);
    form.parentNode.replaceChild(oldForm, form);
    const newForm = oldForm; // Ahora trabajamos con el nuevo elemento

    // --- NUEVAS LLAMADAS PARA RESTRINGIR LA ENTRADA NUMÉRICA ---
    enforceNumericInput("montoAB", true); // Permitir decimales
    enforceNumericInput("montoOtro", true); // Permitir decimales
    // -------------------------------------------------------------
    newForm.addEventListener("submit", async function (event) {
        event.preventDefault(); // Evitar el envío por defecto
        formMessages.innerHTML = "";
        newForm
            .querySelectorAll(".validation-error")
            .forEach((el) => el.classList.add("hidden"));

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
                newForm.reset();
                toastIt(result.message); // Usar toastIt directamente
            } else {
                let errorMessage =
                    result.message || "Ocurrió un error inesperado.";

                if (response.status === 422 && result.errors) {
                    errorMessage =
                        "Falló la validación. Por favor, revisa tus entradas.";
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
            console.error("Error:", error);
            toastIt("Error de conexión: " + error.message); // Usar toastIt directamente
        }
    });
};
var initializeAuditorRecordsDeletion = function () {
    const recordsContainer = document.getElementById("form-content-container");
    if (!recordsContainer) {
        console.warn(
            "Contenedor de registros ('form-content-container') no encontrado. No se puede inicializar la eliminación."
        );
        return;
    }

    const formMessages = document.getElementById("records-messages"); // Contenedor para mensajes específicos de la tabla

    // Usamos delegación de eventos porque los botones 'delete-hk-record' se cargan dinámicamente
    // después de que la página inicial ha cargado.
    recordsContainer.addEventListener("click", async function (event) {
        // Verificamos si el clic fue en un botón con la clase 'delete-hk-record'
        if (event.target.classList.contains("delete-auditor-record")) {
            const button = event.target;
            const rowNumber = button.dataset.rowNumber; // Obtenemos el número de fila de su atributo data

            // Pedimos confirmación al usuario
            if (
                !confirm(
                    `¿Estás seguro de que quieres eliminar el registro de la fila ${rowNumber}? Esta acción es irreversible.`
                )
            ) {
                return; // Si el usuario cancela, no hacemos nada
            }

            formMessages.innerHTML = ""; // Limpiamos mensajes previos
            button.disabled = true; // Deshabilitamos el botón para evitar múltiples clics
            button.textContent = "Eliminando..."; // Cambiamos el texto del botón

            try {
                // Enviamos la solicitud DELETE a la ruta de eliminación
                const response = await fetch("/formulario/delete-auditor", {
                    // const response = await fetch("{{ route('formulario.inventoryhk.delete') }}", {
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
                    // Si la respuesta es exitosa (código 2xx)
                    toastIt(result.message, "success"); // Mostramos un toast de éxito
                    // Eliminamos la fila de la tabla visualmente para reflejar el cambio
                    const rowElement = document.getElementById(
                        `row-${rowNumber}`
                    );
                    if (rowElement) {
                        rowElement.remove();
                    }
                    // Mostramos un mensaje de éxito en el contenedor de mensajes de la tabla
                    formMessages.innerHTML = `
                        <div class="success-message">
                            ${result.message}
                        </div>
                    `;
                } else {
                    // Si hubo un error en la respuesta
                    let errorMessage =
                        result.message || "Error al eliminar el registro.";
                    toastIt(errorMessage, "error"); // Mostramos un toast de error
                    // Mostramos un mensaje de error en el contenedor de mensajes de la tabla
                    formMessages.innerHTML = `
                        <div class="error-message">
                            <p class="font-bold">${errorMessage}</p>
                        </div>
                    `;
                }
            } catch (error) {
                // Si hay un error de red o de JavaScript
                console.error(
                    "Error al enviar la solicitud de eliminación:",
                    error
                );
                toastIt(
                    "Error de conexión al eliminar: " + error.message,
                    "error"
                );
                formMessages.innerHTML = `
                    <div class="error-message">
                        <p class="font-bold">Ocurrió un error al conectar con el servidor.</p>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                // Volvemos a habilitar el botón y restauramos su texto
                button.disabled = false;
                button.textContent = "Eliminar";
            }
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
