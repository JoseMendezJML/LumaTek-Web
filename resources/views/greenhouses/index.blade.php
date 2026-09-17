@extends('layouts.app')

@section('title', 'Invernaderos | LumaTek')

@section('page-title', 'Invernaderos')

@section(
    'page-subtitle',
    'Configura y monitorea los invernaderos de tu empresa'
)

@section('content')

<div class="page-container">

    {{-- =========================================================
         FORMULARIO
    ========================================================== --}}

    <section class="panel-card">

        <div class="panel-card-header">

            <div>
                <h2 id="form-title">
                    Configurar invernadero
                </h2>

                <p>
                    Registra los datos principales del invernadero.
                </p>
            </div>

        </div>

        <div class="panel-card-body">

            <div
                id="greenhouse-message"
                class="panel-message"
                style="display: none;"
            ></div>

            <form id="greenhouse-form">

                <input
                    type="hidden"
                    id="greenhouse-id"
                >

                <div class="panel-form-grid">

                    <div class="panel-form-group">

                        <label for="name">
                            Nombre del invernadero *
                        </label>

                        <input
                            type="text"
                            id="name"
                            maxlength="150"
                            placeholder="Ej. Invernadero Principal"
                            required
                        >

                    </div>

                    <div class="panel-form-group">

                        <label for="crop_type">
                            Tipo de cultivo *
                        </label>

                        <input
                            type="text"
                            id="crop_type"
                            maxlength="120"
                            placeholder="Ej. Tomate"
                            required
                        >

                    </div>

                    <div class="panel-form-group">

                        <label for="area">
                            Área *
                        </label>

                        <input
                            type="number"
                            id="area"
                            min="0.01"
                            step="0.01"
                            placeholder="Ej. 500"
                            required
                        >

                        <span class="form-help">
                            Área total en metros cuadrados (m²).
                        </span>

                    </div>

                    <div class="panel-form-group">

                        <label for="nominal_flow">
                            Caudal nominal *
                        </label>

                        <input
                            type="number"
                            id="nominal_flow"
                            min="0.01"
                            step="0.01"
                            placeholder="Ej. 15.5"
                            required
                        >

                        <span class="form-help">
                            Litros por minuto (L/min).
                        </span>

                    </div>

                    <div class="panel-form-group">

                        <label for="planting_date">
                            Fecha de siembra *
                        </label>

                        <input
                            type="date"
                            id="planting_date"
                            required
                        >

                    </div>

                    <div class="panel-form-group">

                        <label for="location">
                            Ubicación *
                        </label>

                        <input
                            type="text"
                            id="location"
                            maxlength="255"
                            placeholder="Ej. Ocosingo, Chiapas"
                            required
                        >

                    </div>

                </div>

                <div class="panel-form-actions">

                    <button
                        type="button"
                        id="cancel-edit-button"
                        class="btn-secondary"
                        style="display: none;"
                    >
                        Cancelar edición
                    </button>

                    <button
                        type="submit"
                        id="save-button"
                        class="btn-primary"
                    >
                        Guardar invernadero
                    </button>

                </div>

            </form>

        </div>

    </section>


    {{-- =========================================================
         LISTADO
    ========================================================== --}}

    <section
        class="panel-card"
        style="margin-top: 24px;"
    >

        <div class="panel-card-header">

            <div>
                <h2>
                    Mis invernaderos
                </h2>

                <p>
                    Monitoreo y configuración de tus invernaderos.
                </p>
            </div>

            <span id="greenhouse-count">
                0 registrados
            </span>

        </div>

        <div class="panel-card-body">

            <div
                id="greenhouses-loading"
                style="text-align: center; padding: 25px;"
            >
                Cargando invernaderos...
            </div>

            <div
                id="greenhouses-empty"
                style="
                    display: none;
                    text-align: center;
                    padding: 35px;
                    color: #758078;
                "
            >
                Aún no tienes invernaderos registrados.
            </div>

            <div
                id="greenhouses-list"
                class="greenhouses-grid"
            ></div>

        </div>

    </section>

</div>

@endsection


@push('styles')

<style>

    .greenhouses-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(320px, 1fr));
        gap: 18px;
    }

    .greenhouse-card {
        border: 1px solid #e0e8e2;
        border-radius: 14px;
        background: #ffffff;
        padding: 18px;
    }

    .greenhouse-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 15px;
    }

    .greenhouse-card-title {
        margin: 0;
        color: #173d27;
        font-size: 17px;
    }

    .greenhouse-status {
        display: inline-flex;
        padding: 5px 9px;
        border-radius: 20px;
        background: #edf8f0;
        color: #176136;
        font-size: 11px;
        font-weight: 700;
    }

    .greenhouse-data {
        display: grid;
        gap: 9px;
        margin-bottom: 18px;
    }

    .greenhouse-data-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 12px;
    }

    .greenhouse-data-row span:first-child {
        color: #7a867e;
    }

    .greenhouse-data-row strong {
        color: #314239;
        text-align: right;
    }

    /* ================================
       TEMPERATURA
       ================================ */

    .temperature-panel {
        margin-top: 18px;
        padding: 18px;
        border-radius: 12px;
        background: #f7faf8;
        border: 1px solid #e0e8e2;
    }

    .temperature-header {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
    }

    .temperature-title {
        margin: 0;
        color: #4b5b51;
        font-size: 12px;
        font-weight: 700;
    }

    .temperature-connection {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .connection-connected {
        background: #e9f7ed;
        color: #176136;
    }

    .connection-disconnected {
        background: #fff1f1;
        color: #a22626;
    }

    .connection-no-data {
        background: #f2f3f2;
        color: #6d756f;
    }

    .temperature-value {
        margin-top: 10px;
        color: #173d27;
        font-size: 34px;
        font-weight: 700;
        line-height: 1;
    }

    .temperature-detail {
        margin-top: 8px;
        color: #738078;
        font-size: 11px;
    }

    .temperature-alert {
        display: none;
        margin-top: 12px;
        padding: 9px 11px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
    }

    .temperature-alert.high,
    .temperature-alert.low {
        display: block;
        background: #fff3e8;
        border: 1px solid #efcda9;
        color: #9b561d;
    }

    .temperature-alert.normal {
        display: block;
        background: #edf8f0;
        border: 1px solid #bde0c6;
        color: #176136;
    }

    /* ================================
       UMBRALES
       ================================ */

    .threshold-container {
        padding-top: 14px;
        margin-top: 14px;
        border-top: 1px solid #edf1ee;
    }

    .threshold-container h4 {
        margin: 0 0 10px;
        color: #425448;
        font-size: 12px;
    }

    .threshold-item {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 5px 0;
        font-size: 11px;
        color: #68756c;
    }

    .greenhouse-card-actions {
        margin-top: 16px;
        display: flex;
        justify-content: flex-end;
    }

</style>

@endpush


@push('scripts')

<script>

    const token =
        sessionStorage.getItem(
            'lumatek_access_token'
        );

    const greenhouseForm =
        document.getElementById(
            'greenhouse-form'
        );

    const greenhouseId =
        document.getElementById(
            'greenhouse-id'
        );

    const message =
        document.getElementById(
            'greenhouse-message'
        );

    const saveButton =
        document.getElementById(
            'save-button'
        );

    const cancelEditButton =
        document.getElementById(
            'cancel-edit-button'
        );

    const formTitle =
        document.getElementById(
            'form-title'
        );

    const list =
        document.getElementById(
            'greenhouses-list'
        );

    const loading =
        document.getElementById(
            'greenhouses-loading'
        );

    const empty =
        document.getElementById(
            'greenhouses-empty'
        );

    const count =
        document.getElementById(
            'greenhouse-count'
        );


    document.getElementById(
        'planting_date'
    ).max =
        new Date()
            .toISOString()
            .split('T')[0];


    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }


    function variableName(variable) {

        const names = {

            temperature:
                'Temperatura',

            soil_humidity:
                'Humedad del suelo',

            ambient_humidity:
                'Humedad ambiental'

        };

        return names[variable]
            ?? variable;
    }


    function showMessage(
        text,
        type = 'success'
    ) {

        message.className =
            'panel-message ' +
            (
                type === 'success'
                    ? 'panel-message-success'
                    : 'panel-message-error'
            );

        message.textContent =
            text;

        message.style.display =
            'block';

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Temperatura actual
    |--------------------------------------------------------------------------
    */

    async function loadTemperature(
        greenhouseIdValue,
        card
    ) {

        const valueElement =
            card.querySelector(
                '.temperature-value'
            );

        const connectionElement =
            card.querySelector(
                '.temperature-connection'
            );

        const detailElement =
            card.querySelector(
                '.temperature-detail'
            );

        const alertElement =
            card.querySelector(
                '.temperature-alert'
            );


        try {

            const response =
                await fetch(
                    `/api/greenhouses/${greenhouseIdValue}/temperature/current`,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (response.status === 401) {

                sessionStorage.clear();

                window.location.href =
                    '/login';

                return;
            }


            const result =
                await response.json();

            const data =
                result.data;


            /*
            |--------------------------------------------------------------------------
            | Sin sensor
            |--------------------------------------------------------------------------
            */

            if (
                data.connection_status ===
                'no_sensor'
            ) {

                valueElement.textContent =
                    '-- °C';

                connectionElement.textContent =
                    'Sin sensor';

                connectionElement.className =
                    'temperature-connection connection-no-data';

                detailElement.textContent =
                    'No hay un sensor de temperatura configurado.';

                alertElement.style.display =
                    'none';

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Sensor sin lecturas
            |--------------------------------------------------------------------------
            */

            if (
                data.connection_status ===
                'no_data'
            ) {

                valueElement.textContent =
                    '-- °C';

                connectionElement.textContent =
                    'Sin datos';

                connectionElement.className =
                    'temperature-connection connection-no-data';

                detailElement.textContent =
                    'El sensor aún no ha enviado lecturas.';

                alertElement.style.display =
                    'none';

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Temperatura
            |--------------------------------------------------------------------------
            */

            valueElement.textContent =
                Number(
                    data.temperature
                ).toFixed(1)
                + ' °C';


            /*
            |--------------------------------------------------------------------------
            | Estado de conexión
            |--------------------------------------------------------------------------
            */

            connectionElement.textContent =
                data.connection_label;


            if (
                data.connection_status ===
                'connected'
            ) {

                connectionElement.className =
                    'temperature-connection connection-connected';

            } else {

                connectionElement.className =
                    'temperature-connection connection-disconnected';

            }


            /*
            |--------------------------------------------------------------------------
            | Última lectura
            |--------------------------------------------------------------------------
            */

            const minutes =
                Number(
                    data.minutes_since_last_reading
                );


            if (minutes === 0) {

                detailElement.textContent =
                    'Última lectura: hace menos de 1 minuto';

            } else if (minutes === 1) {

                detailElement.textContent =
                    'Última lectura: hace 1 minuto';

            } else {

                detailElement.textContent =
                    `Última lectura: hace ${minutes} minutos`;

            }


            /*
            |--------------------------------------------------------------------------
            | Alerta por umbral
            |--------------------------------------------------------------------------
            */

            alertElement.className =
                'temperature-alert';


            if (
                data.alert_status ===
                'high'
            ) {

                alertElement.classList.add(
                    'high'
                );

                alertElement.textContent =
                    '⚠ Temperatura superior al umbral configurado.';

            }

            else if (
                data.alert_status ===
                'low'
            ) {

                alertElement.classList.add(
                    'low'
                );

                alertElement.textContent =
                    '⚠ Temperatura inferior al umbral configurado.';

            }

            else {

                alertElement.classList.add(
                    'normal'
                );

                alertElement.textContent =
                    '✓ Temperatura dentro del rango configurado.';

            }


        } catch (error) {

            valueElement.textContent =
                '-- °C';

            connectionElement.textContent =
                'Error';

            connectionElement.className =
                'temperature-connection connection-disconnected';

            detailElement.textContent =
                'No fue posible obtener la temperatura.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Cargar invernaderos
    |--------------------------------------------------------------------------
    */

    async function loadGreenhouses() {

        loading.style.display =
            'block';

        empty.style.display =
            'none';

        list.innerHTML = '';


        try {

            const response =
                await fetch(
                    '/api/greenhouses',
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (response.status === 401) {

                sessionStorage.clear();

                window.location.href =
                    '/login';

                return;
            }


            const result =
                await response.json();

            const greenhouses =
                result.data ?? [];


            loading.style.display =
                'none';


            count.textContent =
                `${greenhouses.length} registrado${greenhouses.length === 1 ? '' : 's'}`;


            if (greenhouses.length === 0) {

                empty.style.display =
                    'block';

                return;
            }


            greenhouses.forEach(
                function (greenhouse) {

                    const thresholds =
                        greenhouse.thresholds ?? [];


                    const thresholdsHtml =
                        thresholds
                            .map(function (threshold) {

                                return `
                                    <div class="threshold-item">

                                        <span>
                                            ${escapeHtml(
                                                variableName(
                                                    threshold.variable
                                                )
                                            )}
                                        </span>

                                        <strong>
                                            ${escapeHtml(
                                                threshold.min_value
                                            )}
                                            -
                                            ${escapeHtml(
                                                threshold.max_value
                                            )}
                                            ${escapeHtml(
                                                threshold.unit
                                            )}
                                        </strong>

                                    </div>
                                `;

                            })
                            .join('');


                    const card =
                        document.createElement(
                            'article'
                        );


                    card.className =
                        'greenhouse-card';


                    card.innerHTML = `

                        <div class="greenhouse-card-header">

                            <h3 class="greenhouse-card-title">
                                ${escapeHtml(greenhouse.name)}
                            </h3>

                            <span class="greenhouse-status">
                                ${greenhouse.status === 'active'
                                    ? 'Activo'
                                    : 'Inactivo'}
                            </span>

                        </div>


                        <div class="greenhouse-data">

                            <div class="greenhouse-data-row">
                                <span>Cultivo</span>

                                <strong>
                                    ${escapeHtml(
                                        greenhouse.crop_type
                                    )}
                                </strong>
                            </div>

                            <div class="greenhouse-data-row">
                                <span>Área</span>

                                <strong>
                                    ${escapeHtml(
                                        greenhouse.area
                                    )} m²
                                </strong>
                            </div>

                            <div class="greenhouse-data-row">
                                <span>Ubicación</span>

                                <strong>
                                    ${escapeHtml(
                                        greenhouse.location
                                    )}
                                </strong>
                            </div>

                            <div class="greenhouse-data-row">
                                <span>Fecha de siembra</span>

                                <strong>
                                    ${escapeHtml(
                                        greenhouse.planting_date
                                            ?.split('T')[0]
                                    )}
                                </strong>
                            </div>

                            <div class="greenhouse-data-row">
                                <span>Caudal nominal</span>

                                <strong>
                                    ${escapeHtml(
                                        greenhouse.nominal_flow
                                    )} L/min
                                </strong>
                            </div>

                        </div>


                        <div class="temperature-panel">

                            <div class="temperature-header">

                                <h4 class="temperature-title">
                                    Temperatura actual
                                </h4>

                                <span
                                    class="temperature-connection connection-no-data"
                                >
                                    Consultando...
                                </span>

                            </div>

                            <div class="temperature-value">
                                -- °C
                            </div>

                            <div class="temperature-detail">
                                Consultando última lectura...
                            </div>

                            <div class="temperature-alert"></div>

                        </div>


                        <div class="threshold-container">

                            <h4>
                                Umbrales configurados
                            </h4>

                            ${thresholdsHtml}

                        </div>


                        <div class="greenhouse-card-actions">

                            <button
                                type="button"
                                class="btn-secondary edit-greenhouse"
                            >
                                Editar perfil
                            </button>

                        </div>
                    `;


                    card
                        .querySelector(
                            '.edit-greenhouse'
                        )
                        .addEventListener(
                            'click',
                            function () {

                                startEdit(
                                    greenhouse
                                );

                            }
                        );


                    list.appendChild(
                        card
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Consultar temperatura del invernadero
                    |--------------------------------------------------------------------------
                    */

                    loadTemperature(
                        greenhouse.id,
                        card
                    );

                }
            );


        } catch (error) {

            loading.style.display =
                'none';

            showMessage(
                'No fue posible cargar los invernaderos.',
                'error'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Editar
    |--------------------------------------------------------------------------
    */

    function startEdit(greenhouse) {

        greenhouseId.value =
            greenhouse.id;

        document.getElementById(
            'name'
        ).value =
            greenhouse.name;

        document.getElementById(
            'crop_type'
        ).value =
            greenhouse.crop_type;

        document.getElementById(
            'area'
        ).value =
            greenhouse.area;

        document.getElementById(
            'location'
        ).value =
            greenhouse.location;

        document.getElementById(
            'planting_date'
        ).value =
            greenhouse.planting_date
                ?.split('T')[0];

        document.getElementById(
            'nominal_flow'
        ).value =
            greenhouse.nominal_flow;


        formTitle.textContent =
            'Editar perfil del invernadero';

        saveButton.textContent =
            'Guardar cambios';

        cancelEditButton.style.display =
            'inline-flex';


        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

    }


    /*
    |--------------------------------------------------------------------------
    | Reiniciar formulario
    |--------------------------------------------------------------------------
    */

    function resetForm() {

        greenhouseForm.reset();

        greenhouseId.value =
            '';

        formTitle.textContent =
            'Configurar invernadero';

        saveButton.textContent =
            'Guardar invernadero';

        cancelEditButton.style.display =
            'none';

    }


    cancelEditButton.addEventListener(
        'click',
        resetForm
    );


    /*
    |--------------------------------------------------------------------------
    | Guardar / actualizar
    |--------------------------------------------------------------------------
    */

    greenhouseForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            message.style.display =
                'none';

            saveButton.disabled =
                true;

            saveButton.textContent =
                'Guardando...';


            const id =
                greenhouseId.value;


            const payload = {

                name:
                    document.getElementById(
                        'name'
                    ).value,

                crop_type:
                    document.getElementById(
                        'crop_type'
                    ).value,

                area:
                    document.getElementById(
                        'area'
                    ).value,

                location:
                    document.getElementById(
                        'location'
                    ).value,

                planting_date:
                    document.getElementById(
                        'planting_date'
                    ).value,

                nominal_flow:
                    document.getElementById(
                        'nominal_flow'
                    ).value

            };


            const url =
                id
                    ? `/api/greenhouses/${id}`
                    : '/api/greenhouses';


            const method =
                id
                    ? 'PUT'
                    : 'POST';


            try {

                const response =
                    await fetch(
                        url,
                        {
                            method: method,

                            headers: {

                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${token}`
                            },

                            body:
                                JSON.stringify(
                                    payload
                                )
                        }
                    );


                const result =
                    await response.json();


                if (response.status === 401) {

                    sessionStorage.clear();

                    window.location.href =
                        '/login';

                    return;
                }


                if (!response.ok) {

                    if (result.errors) {

                        const errors =
                            Object.values(
                                result.errors
                            ).flat();

                        showMessage(
                            errors.join(' '),
                            'error'
                        );

                    } else {

                        showMessage(
                            result.message
                            ?? 'No fue posible guardar el invernadero.',
                            'error'
                        );

                    }

                    return;
                }


                showMessage(
                    result.message,
                    'success'
                );


                resetForm();

                await loadGreenhouses();


            } catch (error) {

                showMessage(
                    'No fue posible conectar con el servidor.',
                    'error'
                );

            } finally {

                saveButton.disabled =
                    false;

                saveButton.textContent =
                    greenhouseId.value
                        ? 'Guardar cambios'
                        : 'Guardar invernadero';

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Inicio
    |--------------------------------------------------------------------------
    */

    loadGreenhouses();


    /*
    |--------------------------------------------------------------------------
    | CA01 - Actualización cada 5 minutos
    |--------------------------------------------------------------------------
    |
    | La interfaz vuelve a consultar las lecturas cada 5 minutos.
    | El dispositivo IoT deberá posteriormente enviar también una lectura
    | con esa frecuencia.
    |
    */

    setInterval(
        loadGreenhouses,
        5 * 60 * 1000
    );

</script>

@endpush