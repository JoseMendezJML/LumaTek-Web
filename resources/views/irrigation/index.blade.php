@extends('layouts.app')

@section('title', 'Riego | LumaTek')

@section('page-title', 'Riego')

@section(
    'page-subtitle',
    'Control y seguimiento del riego de tus invernaderos'
)

@section('content')

<div class="irrigation-page">

    {{-- =========================================================
         RIEGO AUTOMÁTICO
    ========================================================== --}}

    @include('irrigation.automatic-settings')


    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <section class="irrigation-summary">

        <article class="summary-card">

            <span class="summary-icon">
                💧
            </span>

            <div>

                <span class="summary-label">
                    Total de riegos
                </span>

                <strong id="summary-total">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card active-card">

            <span class="summary-icon">
                🚿
            </span>

            <div>

                <span class="summary-label">
                    En curso
                </span>

                <strong id="summary-active">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card">

            <span class="summary-icon">
                ✅
            </span>

            <div>

                <span class="summary-label">
                    Completados
                </span>

                <strong id="summary-completed">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card">

            <span class="summary-icon">
                ❌
            </span>

            <div>

                <span class="summary-label">
                    Cancelados
                </span>

                <strong id="summary-cancelled">
                    0
                </strong>

            </div>

        </article>

    </section>


    {{-- =========================================================
         MENSAJES
    ========================================================== --}}

    <div
        id="irrigation-message"
        class="message-box"
        style="display:none;"
    ></div>


    {{-- =========================================================
         INICIAR RIEGO MANUAL
    ========================================================== --}}

    <section class="irrigation-card">

        <div class="section-header">

            <div>

                <h2>
                    Iniciar riego manual
                </h2>

                <p>
                    Selecciona el invernadero y la zona donde se realizará el riego.
                </p>

            </div>


            <span class="manual-badge">
                Manual
            </span>

        </div>


        <form id="irrigation-form">

            <div class="form-grid">

                {{-- INVERNADERO --}}

                <div class="form-group">

                    <label for="greenhouse-select">
                        Invernadero
                    </label>

                    <select
                        id="greenhouse-select"
                        required
                    >

                        <option value="">
                            Selecciona un invernadero
                        </option>

                    </select>

                </div>


                {{-- ZONA --}}

                <div class="form-group">

                    <label for="zone-select">
                        Zona
                    </label>

                    <select
                        id="zone-select"
                        required
                        disabled
                    >

                        <option value="">
                            Selecciona primero un invernadero
                        </option>

                    </select>

                </div>


                {{-- AGUA --}}

                <div class="form-group">

                    <label for="water-liters">
                        Agua estimada (L)
                    </label>

                    <input
                        id="water-liters"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Ej. 10"
                    >

                </div>


                {{-- OBSERVACIONES --}}

                <div class="form-group notes-group">

                    <label for="irrigation-notes">
                        Observaciones
                    </label>

                    <input
                        id="irrigation-notes"
                        type="text"
                        maxlength="1000"
                        placeholder="Ej. Riego de prueba en Zona Principal"
                    >

                </div>

            </div>


            <div class="form-actions">

                <button
                    id="start-irrigation-button"
                    type="submit"
                    class="primary-button"
                >
                    💧 Iniciar riego
                </button>

            </div>

        </form>

    </section>


    {{-- =========================================================
         RIEGOS EN CURSO
    ========================================================== --}}

    <section class="irrigation-card">

        <div class="section-header">

            <div>

                <h2>
                    Riegos en curso
                </h2>

                <p>
                    Riegos que todavía no han finalizado.
                </p>

            </div>


            <button
                type="button"
                id="refresh-button"
                class="secondary-button"
            >
                Actualizar
            </button>

        </div>


        <div
            id="active-irrigations-empty"
            class="empty-state"
            style="display:none;"
        >

            <div class="empty-icon">
                💧
            </div>

            <strong>
                No hay riegos en curso
            </strong>

            <span>
                Los nuevos riegos aparecerán aquí.
            </span>

        </div>


        <div
            id="active-irrigations-list"
            class="active-irrigations-grid"
        ></div>

    </section>


    {{-- =========================================================
         HISTORIAL
    ========================================================== --}}

    <section class="irrigation-card">

        <div class="section-header">

            <div>

                <h2>
                    Historial de riego
                </h2>

                <p>
                    Consulta los eventos registrados anteriormente.
                </p>

            </div>

        </div>


        {{-- FILTROS --}}

        <div class="filters-row">

            <div class="form-group">

                <label for="status-filter">
                    Estado
                </label>

                <select id="status-filter">

                    <option value="">
                        Todos
                    </option>

                    <option value="started">
                        En curso
                    </option>

                    <option value="completed">
                        Completados
                    </option>

                    <option value="cancelled">
                        Cancelados
                    </option>

                    <option value="failed">
                        Fallidos
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="mode-filter">
                    Modo
                </label>

                <select id="mode-filter">

                    <option value="">
                        Todos
                    </option>

                    <option value="manual">
                        Manual
                    </option>

                    <option value="automatic">
                        Automático
                    </option>

                </select>

            </div>

        </div>


        <div
            id="history-loading"
            class="loading-state"
        >
            Cargando historial...
        </div>


        <div
            id="history-empty"
            class="empty-state"
            style="display:none;"
        >

            <div class="empty-icon">
                📋
            </div>

            <strong>
                Sin registros
            </strong>

            <span>
                Todavía no hay eventos de riego con estos filtros.
            </span>

        </div>


        <div
            id="history-list"
            class="history-list"
        ></div>

    </section>

</div>

@endsection


{{-- =============================================================
     ESTILOS
============================================================= --}}

@push('styles')

<style>

    .irrigation-page {
        width: 100%;
    }


    /* =========================================================
       RESUMEN
    ========================================================== */

    .irrigation-summary {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 14px;

        margin-bottom: 22px;
    }


    .summary-card {
        min-height: 95px;

        display: flex;
        align-items: center;
        gap: 13px;

        padding: 17px;

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 13px;
    }


    .summary-icon {
        width: 42px;
        height: 42px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        border-radius: 11px;

        background: #edf7f0;

        font-size: 20px;
    }


    .summary-label {
        display: block;

        color: #748078;

        font-size: 10px;
    }


    .summary-card strong {
        display: block;

        margin-top: 4px;

        color: #173d27;

        font-size: 25px;
    }


    .active-card {
        border-left: 4px solid #3a8c5c;
    }


    /* =========================================================
       CARDS
    ========================================================== */

    .irrigation-card {
        margin-bottom: 20px;

        padding: 20px;

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 14px;
    }


    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 15px;

        margin-bottom: 18px;
    }


    .section-header h2 {
        margin: 0;

        color: #173d27;

        font-size: 17px;
    }


    .section-header p {
        margin: 5px 0 0;

        color: #78837c;

        font-size: 10px;
    }


    .manual-badge {
        padding: 6px 10px;

        border-radius: 20px;

        background: #edf7f0;

        color: #176136;

        font-size: 9px;
        font-weight: 700;
    }


    /* =========================================================
       FORMULARIOS
    ========================================================== */

    .form-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 14px;
    }


    .form-group {
        display: flex;
        flex-direction: column;

        gap: 6px;
    }


    .notes-group {
        grid-column:
            span 3;
    }


    .form-group label {
        color: #55645b;

        font-size: 10px;
        font-weight: 700;
    }


    .form-group input,
    .form-group select {
        width: 100%;

        min-height: 40px;

        padding: 9px 11px;

        border: 1px solid #dce5de;
        border-radius: 8px;

        background: #ffffff;

        color: #314239;

        font-size: 11px;

        outline: none;
    }


    .form-group input:focus,
    .form-group select:focus {
        border-color: #4c8b62;

        box-shadow:
            0 0 0 2px rgba(76, 139, 98, .10);
    }


    .form-group select:disabled {
        background: #f3f5f4;

        color: #929a95;
    }


    .form-actions {
        display: flex;
        justify-content: flex-end;

        margin-top: 16px;
    }


    /* =========================================================
       BOTONES
    ========================================================== */

    .primary-button,
    .secondary-button,
    .danger-button,
    .success-button {
        min-height: 38px;

        padding: 9px 14px;

        border: 0;
        border-radius: 8px;

        cursor: pointer;

        font-size: 10px;
        font-weight: 700;
    }


    .primary-button,
    .success-button {
        background: #176136;

        color: #ffffff;
    }


    .primary-button:hover,
    .success-button:hover {
        background: #104c2a;
    }


    .secondary-button {
        border: 1px solid #dbe4dd;

        background: #ffffff;

        color: #526159;
    }


    .secondary-button:hover {
        background: #f4f7f5;
    }


    .danger-button {
        background: #fff0f0;

        color: #a22e2e;
    }


    .danger-button:hover {
        background: #f9dddd;
    }


    button:disabled {
        opacity: .55;

        cursor: not-allowed;
    }


    /* =========================================================
       RIEGOS ACTIVOS
    ========================================================== */

    .active-irrigations-grid {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 14px;
    }


    .active-irrigation-card {
        padding: 16px;

        border: 1px solid #dfe7e1;
        border-left: 4px solid #3a8c5c;

        border-radius: 11px;

        background: #fbfdfb;
    }


    .active-irrigation-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        gap: 12px;
    }


    .active-irrigation-header h3 {
        margin: 0;

        color: #294336;

        font-size: 13px;
    }


    .active-irrigation-header p {
        margin: 4px 0 0;

        color: #7c8880;

        font-size: 9px;
    }


    .status-running {
        padding: 5px 8px;

        border-radius: 20px;

        background: #e8f6ed;

        color: #176136;

        font-size: 8px;
        font-weight: 700;
    }


    .irrigation-info-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 9px;

        margin-top: 14px;
    }


    .info-box {
        padding: 9px;

        border-radius: 8px;

        background: #f2f7f3;
    }


    .info-box span {
        display: block;

        color: #7d8880;

        font-size: 8px;
    }


    .info-box strong {
        display: block;

        margin-top: 4px;

        color: #33473a;

        font-size: 11px;
    }


    .active-actions {
        display: flex;

        gap: 8px;

        margin-top: 14px;
    }


    /* =========================================================
       FILTROS
    ========================================================== */

    .filters-row {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 220px));

        gap: 12px;

        margin-bottom: 18px;
    }


    /* =========================================================
       HISTORIAL
    ========================================================== */

    .history-list {
        display: grid;

        gap: 10px;
    }


    .history-item {
        display: grid;

        grid-template-columns:
            minmax(160px, 1.3fr)
            repeat(4, minmax(90px, .7fr));

        gap: 12px;

        align-items: center;

        padding: 13px 14px;

        border: 1px solid #e3e9e5;
        border-radius: 10px;
    }


    .history-main strong {
        display: block;

        color: #33473a;

        font-size: 11px;
    }


    .history-main span {
        display: block;

        margin-top: 4px;

        color: #7c8880;

        font-size: 9px;
    }


    .history-column span {
        display: block;

        color: #7e8982;

        font-size: 8px;
    }


    .history-column strong {
        display: block;

        margin-top: 4px;

        color: #34473b;

        font-size: 10px;
    }


    .status-badge {
        display: inline-flex;

        padding: 5px 8px;

        border-radius: 20px;

        font-size: 8px;
        font-weight: 700;
    }


    .status-started {
        background: #e8f6ed;

        color: #176136;
    }


    .status-completed {
        background: #eaf4ff;

        color: #2d6591;
    }


    .status-cancelled,
    .status-failed {
        background: #fff0f0;

        color: #a22e2e;
    }


    /* =========================================================
       MENSAJES Y ESTADOS
    ========================================================== */

    .empty-state,
    .loading-state {
        padding: 32px 15px;

        text-align: center;

        color: #758079;
    }


    .empty-state strong {
        display: block;

        margin-top: 7px;

        color: #425249;

        font-size: 12px;
    }


    .empty-state span {
        display: block;

        margin-top: 4px;

        font-size: 9px;
    }


    .empty-icon {
        font-size: 26px;
    }


    .message-box {
        margin-bottom: 18px;

        padding: 11px 13px;

        border-radius: 9px;

        font-size: 10px;
    }


    .message-success {
        background: #eaf7ee;

        color: #176136;

        border: 1px solid #cae8d3;
    }


    .message-error {
        background: #fff0f0;

        color: #a22e2e;

        border: 1px solid #f1cccc;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 1050px) {

        .irrigation-summary {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .form-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .notes-group {
            grid-column:
                span 2;
        }


        .active-irrigations-grid {
            grid-template-columns: 1fr;
        }


        .history-item {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }


    @media (max-width: 650px) {

        .irrigation-summary,
        .form-grid,
        .filters-row,
        .history-item {
            grid-template-columns: 1fr;
        }


        .notes-group {
            grid-column:
                span 1;
        }


        .section-header {
            align-items: flex-start;
            flex-direction: column;
        }


        .active-actions {
            flex-direction: column;
        }
    }

</style>

@endpush


{{-- =============================================================
     JAVASCRIPT
============================================================= --}}

@push('scripts')

<script>

    /*
    |--------------------------------------------------------------------------
    | TOKEN
    |--------------------------------------------------------------------------
    */

    const irrigationToken =
        sessionStorage.getItem(
            'lumatek_access_token'
        );


    if (!irrigationToken) {

        window.location.href =
            '/login';
    }


    /*
    |--------------------------------------------------------------------------
    | ELEMENTOS
    |--------------------------------------------------------------------------
    */

    const greenhouseSelect =
        document.getElementById(
            'greenhouse-select'
        );


    const zoneSelect =
        document.getElementById(
            'zone-select'
        );


    const waterLitersInput =
        document.getElementById(
            'water-liters'
        );


    const notesInput =
        document.getElementById(
            'irrigation-notes'
        );


    const irrigationForm =
        document.getElementById(
            'irrigation-form'
        );


    const startButton =
        document.getElementById(
            'start-irrigation-button'
        );


    const activeList =
        document.getElementById(
            'active-irrigations-list'
        );


    const activeEmpty =
        document.getElementById(
            'active-irrigations-empty'
        );


    const historyList =
        document.getElementById(
            'history-list'
        );


    const historyEmpty =
        document.getElementById(
            'history-empty'
        );


    const historyLoading =
        document.getElementById(
            'history-loading'
        );


    const statusFilter =
        document.getElementById(
            'status-filter'
        );


    const modeFilter =
        document.getElementById(
            'mode-filter'
        );


    const refreshButton =
        document.getElementById(
            'refresh-button'
        );


    const messageBox =
        document.getElementById(
            'irrigation-message'
        );


    /*
    |--------------------------------------------------------------------------
    | UTILIDADES
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const element =
            document.createElement(
                'div'
            );


        element.textContent =
            value ?? '';


        return element.innerHTML;
    }


    function handleUnauthorized(
        response
    ) {

        if (response.status === 401) {

            sessionStorage.removeItem(
                'lumatek_access_token'
            );


            sessionStorage.removeItem(
                'lumatek_user'
            );


            window.location.href =
                '/login';


            return true;
        }


        return false;
    }


    function showMessage(
        text,
        type = 'success'
    ) {

        messageBox.textContent =
            text;


        messageBox.className =
            'message-box ' +
            (
                type === 'success'
                    ? 'message-success'
                    : 'message-error'
            );


        messageBox.style.display =
            'block';


        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    }


    function hideMessage() {

        messageBox.style.display =
            'none';
    }


    function formatDate(
        value
    ) {

        if (!value) {

            return '—';
        }


        const normalized =
            value.includes('T')
                ? value
                : value.replace(
                    ' ',
                    'T'
                );


        const date =
            new Date(
                normalized
            );


        return date.toLocaleString(
            'es-MX',
            {
                dateStyle:
                    'short',

                timeStyle:
                    'short',
            }
        );
    }


    function humidityValue(
        value
    ) {

        if (
            value === null
            ||
            value === undefined
        ) {

            return '—';
        }


        return `${Number(value).toFixed(1)} %`;
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR INVERNADEROS
    |--------------------------------------------------------------------------
    */

    async function loadGreenhouses() {

        try {

            const response =
                await fetch(
                    '/api/greenhouses',
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            const greenhouses =
                Array.isArray(result.data)
                    ? result.data
                    : (
                        Array.isArray(result)
                            ? result
                            : []
                    );


            greenhouseSelect.innerHTML =
                `
                    <option value="">
                        Selecciona un invernadero
                    </option>
                `;


            greenhouses.forEach(
                greenhouse => {

                    const option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        greenhouse.id;


                    option.textContent =
                        greenhouse.name;


                    greenhouseSelect.appendChild(
                        option
                    );
                }
            );


        } catch (error) {

            showMessage(
                'No fue posible cargar los invernaderos.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR ZONAS
    |--------------------------------------------------------------------------
    */

    async function loadZones(
        greenhouseId
    ) {

        zoneSelect.disabled =
            true;


        zoneSelect.innerHTML =
            `
                <option value="">
                    Cargando zonas...
                </option>
            `;


        if (!greenhouseId) {

            zoneSelect.innerHTML =
                `
                    <option value="">
                        Selecciona primero un invernadero
                    </option>
                `;


            return;
        }


        try {

            const response =
                await fetch(
                    `/api/greenhouses/${greenhouseId}/zones`,
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            const zones =
                Array.isArray(result.data)
                    ? result.data
                    : [];


            zoneSelect.innerHTML =
                `
                    <option value="">
                        Selecciona una zona
                    </option>
                `;


            zones
                .filter(
                    zone =>
                        !zone.status
                        ||
                        zone.status === 'active'
                )
                .forEach(
                    zone => {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            zone.id;


                        option.textContent =
                            zone.name;


                        zoneSelect.appendChild(
                            option
                        );
                    }
                );


            zoneSelect.disabled =
                false;


        } catch (error) {

            zoneSelect.innerHTML =
                `
                    <option value="">
                        Error al cargar zonas
                    </option>
                `;


            showMessage(
                'No fue posible cargar las zonas.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INICIAR RIEGO MANUAL
    |--------------------------------------------------------------------------
    */

    async function startIrrigation(
        event
    ) {

        event.preventDefault();


        hideMessage();


        const zoneId =
            zoneSelect.value;


        if (!zoneId) {

            showMessage(
                'Selecciona una zona.',
                'error'
            );


            return;
        }


        const body = {};


        if (
            waterLitersInput.value !== ''
        ) {

            body.water_liters =
                Number(
                    waterLitersInput.value
                );
        }


        if (
            notesInput.value.trim()
            !== ''
        ) {

            body.notes =
                notesInput.value.trim();
        }


        startButton.disabled =
            true;


        startButton.textContent =
            'Iniciando...';


        try {

            const response =
                await fetch(
                    `/api/zones/${zoneId}/irrigation/start`,
                    {
                        method:
                            'POST',

                        headers: {

                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },

                        body:
                            JSON.stringify(
                                body
                            ),
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            if (!response.ok) {

                showMessage(
                    result.message
                    ?? 'No fue posible iniciar el riego.',
                    'error'
                );


                return;
            }


            showMessage(
                result.message
                ?? 'Riego iniciado correctamente.'
            );


            waterLitersInput.value =
                '';


            notesInput.value =
                '';


            await loadIrrigations();


        } catch (error) {

            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );


        } finally {

            startButton.disabled =
                false;


            startButton.textContent =
                '💧 Iniciar riego';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINALIZAR
    |--------------------------------------------------------------------------
    */

    async function completeIrrigation(
        id
    ) {

        const confirmed =
            confirm(
                '¿Deseas finalizar este riego?'
            );


        if (!confirmed) {

            return;
        }


        hideMessage();


        try {

            const response =
                await fetch(
                    `/api/irrigation-events/${id}/complete`,
                    {
                        method:
                            'PATCH',

                        headers: {

                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },

                        body:
                            JSON.stringify({}),
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            if (!response.ok) {

                showMessage(
                    result.message
                    ?? 'No fue posible finalizar el riego.',
                    'error'
                );


                return;
            }


            showMessage(
                result.message
                ?? 'Riego finalizado correctamente.'
            );


            await loadIrrigations();


        } catch (error) {

            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CANCELAR
    |--------------------------------------------------------------------------
    */

    async function cancelIrrigation(
        id
    ) {

        const confirmed =
            confirm(
                '¿Deseas cancelar este riego?'
            );


        if (!confirmed) {

            return;
        }


        hideMessage();


        try {

            const response =
                await fetch(
                    `/api/irrigation-events/${id}/cancel`,
                    {
                        method:
                            'PATCH',

                        headers: {

                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },

                        body:
                            JSON.stringify({
                                notes:
                                    'Riego cancelado desde la interfaz web.',
                            }),
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            if (!response.ok) {

                showMessage(
                    result.message
                    ?? 'No fue posible cancelar el riego.',
                    'error'
                );


                return;
            }


            showMessage(
                result.message
                ?? 'Riego cancelado correctamente.'
            );


            await loadIrrigations();


        } catch (error) {

            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR RIEGOS
    |--------------------------------------------------------------------------
    */

    async function loadIrrigations() {

        historyLoading.style.display =
            'block';


        historyEmpty.style.display =
            'none';


        historyList.innerHTML =
            '';


        activeList.innerHTML =
            '';


        try {

            const params =
                new URLSearchParams();


            if (statusFilter.value) {

                params.append(
                    'status',
                    statusFilter.value
                );
            }


            if (modeFilter.value) {

                params.append(
                    'mode',
                    modeFilter.value
                );
            }


            const url =
                params.toString()
                    ? `/api/irrigation-events?${params.toString()}`
                    : '/api/irrigation-events';


            const response =
                await fetch(
                    url,
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },
                    }
                );


            if (
                handleUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            if (!response.ok) {

                showMessage(
                    result.message
                    ?? 'No fue posible cargar el historial.',
                    'error'
                );


                return;
            }


            const filteredEvents =
                result.data ?? [];


            /*
            |--------------------------------------------------------------------------
            | Listado completo para resumen y activos
            |--------------------------------------------------------------------------
            */

            const allResponse =
                await fetch(
                    '/api/irrigation-events',
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${irrigationToken}`,
                        },
                    }
                );


            if (
                handleUnauthorized(
                    allResponse
                )
            ) {
                return;
            }


            const allResult =
                await allResponse.json();


            const allEvents =
                allResult.data ?? [];


            renderSummary(
                allEvents
            );


            renderActive(
                allEvents.filter(
                    item =>
                        item.status
                        === 'started'
                )
            );


            renderHistory(
                filteredEvents
            );


        } catch (error) {

            showMessage(
                'No fue posible cargar los eventos de riego.',
                'error'
            );


        } finally {

            historyLoading.style.display =
                'none';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESUMEN
    |--------------------------------------------------------------------------
    */

    function renderSummary(
        events
    ) {

        document.getElementById(
            'summary-total'
        ).textContent =
            events.length;


        document.getElementById(
            'summary-active'
        ).textContent =
            events.filter(
                event =>
                    event.status
                    === 'started'
            ).length;


        document.getElementById(
            'summary-completed'
        ).textContent =
            events.filter(
                event =>
                    event.status
                    === 'completed'
            ).length;


        document.getElementById(
            'summary-cancelled'
        ).textContent =
            events.filter(
                event =>
                    event.status
                    === 'cancelled'
            ).length;
    }


    /*
    |--------------------------------------------------------------------------
    | RIEGOS ACTIVOS
    |--------------------------------------------------------------------------
    */

    function renderActive(
        events
    ) {

        activeList.innerHTML =
            '';


        if (!events.length) {

            activeEmpty.style.display =
                'block';


            return;
        }


        activeEmpty.style.display =
            'none';


        events.forEach(
            event => {

                const card =
                    document.createElement(
                        'article'
                    );


                card.className =
                    'active-irrigation-card';


                const modeLabel =
                    event.mode === 'automatic'
                        ? '🤖 Automático'
                        : '👤 Manual';


                card.innerHTML = `

                    <div class="active-irrigation-header">

                        <div>

                            <h3>
                                💧 ${escapeHtml(
                                    event.zone?.name
                                    ?? 'Zona'
                                )}
                            </h3>

                            <p>

                                ${escapeHtml(
                                    event.greenhouse?.name
                                    ?? 'Sin invernadero'
                                )}

                                ·

                                ${modeLabel}

                            </p>

                        </div>


                        <span class="status-running">
                            En curso
                        </span>

                    </div>


                    <div class="irrigation-info-grid">

                        <div class="info-box">

                            <span>
                                Humedad inicial
                            </span>

                            <strong>
                                ${humidityValue(
                                    event.soil_humidity_before
                                )}
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Agua
                            </span>

                            <strong>

                                ${
                                    event.water_liters !== null
                                        ? `${Number(
                                            event.water_liters
                                        ).toFixed(2)} L`
                                        : '—'
                                }

                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Inicio
                            </span>

                            <strong>
                                ${escapeHtml(
                                    formatDate(
                                        event.started_at
                                    )
                                )}
                            </strong>

                        </div>

                    </div>


                    <div class="active-actions">

                        <button
                            type="button"
                            class="success-button"
                            onclick="completeIrrigation(${event.id})"
                        >
                            ✓ Finalizar
                        </button>


                        <button
                            type="button"
                            class="danger-button"
                            onclick="cancelIrrigation(${event.id})"
                        >
                            Cancelar
                        </button>

                    </div>
                `;


                activeList.appendChild(
                    card
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HISTORIAL
    |--------------------------------------------------------------------------
    */

    function renderHistory(
        events
    ) {

        historyList.innerHTML =
            '';


        if (!events.length) {

            historyEmpty.style.display =
                'block';


            return;
        }


        historyEmpty.style.display =
            'none';


        events.forEach(
            event => {

                const item =
                    document.createElement(
                        'article'
                    );


                item.className =
                    'history-item';


                const modeLabel =
                    event.mode === 'automatic'
                        ? 'Automático'
                        : 'Manual';


                item.innerHTML = `

                    <div class="history-main">

                        <strong>
                            ${escapeHtml(
                                event.zone?.name
                                ?? 'Zona'
                            )}
                        </strong>

                        <span>

                            ${escapeHtml(
                                event.greenhouse?.name
                                ?? 'Sin invernadero'
                            )}

                            ·

                            ${modeLabel}

                        </span>

                    </div>


                    <div class="history-column">

                        <span>
                            Estado
                        </span>

                        <strong>

                            <span
                                class="
                                    status-badge
                                    status-${escapeHtml(
                                        event.status
                                    )}
                                "
                            >

                                ${escapeHtml(
                                    event.status_label
                                )}

                            </span>

                        </strong>

                    </div>


                    <div class="history-column">

                        <span>
                            Humedad
                        </span>

                        <strong>

                            ${humidityValue(
                                event.soil_humidity_before
                            )}

                            →

                            ${humidityValue(
                                event.soil_humidity_after
                            )}

                        </strong>

                    </div>


                    <div class="history-column">

                        <span>
                            Agua
                        </span>

                        <strong>

                            ${
                                event.water_liters !== null
                                    ? `${Number(
                                        event.water_liters
                                    ).toFixed(2)} L`
                                    : '—'
                            }

                        </strong>

                    </div>


                    <div class="history-column">

                        <span>
                            Inicio
                        </span>

                        <strong>
                            ${escapeHtml(
                                formatDate(
                                    event.started_at
                                )
                            )}
                        </strong>

                    </div>
                `;


                historyList.appendChild(
                    item
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EVENTOS
    |--------------------------------------------------------------------------
    */

    greenhouseSelect.addEventListener(
        'change',
        function () {

            loadZones(
                this.value
            );
        }
    );


    irrigationForm.addEventListener(
        'submit',
        startIrrigation
    );


    statusFilter.addEventListener(
        'change',
        loadIrrigations
    );


    modeFilter.addEventListener(
        'change',
        loadIrrigations
    );


    refreshButton.addEventListener(
        'click',
        loadIrrigations
    );


    /*
    |--------------------------------------------------------------------------
    | INICIALIZAR
    |--------------------------------------------------------------------------
    */

    loadGreenhouses();

    loadIrrigations();

</script>

@endpush