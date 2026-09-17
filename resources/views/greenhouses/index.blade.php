@extends('layouts.app')

@section('title', 'Invernaderos | LumaTek')

@section('page-title', 'Invernaderos')

@section(
    'page-subtitle',
    'Configura y administra los invernaderos de tu empresa'
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

                    {{-- Nombre --}}
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


                    {{-- Cultivo --}}
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


                    {{-- Área --}}
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


                    {{-- Caudal --}}
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


                    {{-- Fecha --}}
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


                    {{-- Ubicación --}}
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
                    Invernaderos registrados en tu empresa.
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
            repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
    }

    .greenhouse-card {
        border: 1px solid #e0e8e2;
        border-radius: 12px;
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


    /*
    |--------------------------------------------------------------------------
    | Fecha máxima
    |--------------------------------------------------------------------------
    */

    document.getElementById(
        'planting_date'
    ).max =
        new Date()
            .toISOString()
            .split('T')[0];


    /*
    |--------------------------------------------------------------------------
    | Escapar texto
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }


    /*
    |--------------------------------------------------------------------------
    | Nombres de variables
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Mostrar mensajes
    |--------------------------------------------------------------------------
    */

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


                        <div class="threshold-container">

                            <h4>
                                Umbrales iniciales
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


                    list.appendChild(card);

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
    | Cancelar edición
    |--------------------------------------------------------------------------
    */

    function resetForm() {

        greenhouseForm.reset();

        greenhouseId.value = '';

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

</script>

@endpush