@extends('layouts.app')

@section('title', 'Historial | LumaTek')

@section('page-title', 'Historial')

@section(
    'page-subtitle',
    'Consulta y analiza las mediciones registradas en tus invernaderos'
)

@section('content')

<div class="history-page">

    {{-- =========================================================
         FILTROS
    ========================================================== --}}

    <section class="history-card">

        <div class="section-header">

            <div>
                <h2>Filtros de consulta</h2>

                <p>
                    Selecciona los datos que deseas analizar.
                </p>
            </div>

        </div>


        <form id="history-filter-form">

            <div class="filters-grid">

                <div class="form-group">

                    <label for="history-greenhouse">
                        Invernadero
                    </label>

                    <select id="history-greenhouse">

                        <option value="">
                            Todos los invernaderos
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="history-zone">
                        Zona
                    </label>

                    <select
                        id="history-zone"
                        disabled
                    >

                        <option value="">
                            Todas las zonas
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="history-metric">
                        Tipo de medición
                    </label>

                    <select id="history-metric">

                        <option value="temperature">
                            Temperatura
                        </option>

                        <option value="soil_humidity">
                            Humedad del suelo
                        </option>

                        <option value="ambient_humidity">
                            Humedad ambiental
                        </option>

                    </select>

                </div>


                <div class="form-group">
                    <label for="history-sensor">Sensor (ID opcional)</label>
                    <input id="history-sensor" type="number" min="1" placeholder="Todos los sensores" inputmode="numeric">
                </div>

                <div class="form-group">

                    <label for="history-start-date">
                        Fecha inicial
                    </label>

                    <input
                        id="history-start-date"
                        type="date"
                    >

                </div>


                <div class="form-group">

                    <label for="history-end-date">
                        Fecha final
                    </label>

                    <input
                        id="history-end-date"
                        type="date"
                    >

                </div>


                <div class="form-group">
                    <label for="history-unit">Unidad histórica</label>
                    <select id="history-unit">
                        <option value="">Todas (series separadas)</option>
                        <option value="raw">raw</option>
                        <option value="%">%</option>
                        <option value="°C">°C</option>
                    </select>
                </div>
            </div>
            <div class="history-filter-actions">
                <p>Las unidades y la procedencia se conservan por lectura.</p>
                <button type="submit" id="history-search-button" class="primary-button">Consultar historial</button>
            </div>

        </form>

    </section>


    {{-- =========================================================
         MENSAJES
    ========================================================== --}}

    <div
        id="history-message"
        class="message-box"
        style="display:none;"
    ></div>


    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <section class="summary-grid">

        <article class="summary-card">

            <span class="summary-label">
                Total de lecturas
            </span>

            <strong id="summary-total">
                0
            </strong>

            <small id="summary-total-unit">
                registros
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Valor mínimo
            </span>

            <strong id="summary-minimum">
                —
            </strong>

            <small id="summary-minimum-unit">
                —
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Valor máximo
            </span>

            <strong id="summary-maximum">
                —
            </strong>

            <small id="summary-maximum-unit">
                —
            </small>

        </article>


        <article class="summary-card">

            <span class="summary-label">
                Promedio
            </span>

            <strong id="summary-average">
                —
            </strong>

            <small id="summary-average-unit">
                —
            </small>

        </article>

    </section>


    {{-- =========================================================
         GRÁFICA
    ========================================================== --}}

    <section class="history-card">

        <div class="section-header">

            <div>

                <h2 id="chart-title">
                    Evolución de temperatura
                </h2>

                <p id="chart-subtitle">
                    Comportamiento de las mediciones durante el periodo seleccionado.
                </p>

            </div>

        </div>


        <div
            id="chart-empty"
            class="empty-state"
            style="display:none;"
        >

            <strong>
                No hay datos para graficar
            </strong>

            <span>
                Cambia los filtros o selecciona otro periodo.
            </span>

        </div>


        <div
            id="chart-container"
            class="chart-container"
        >

            <canvas
                id="history-chart"
            ></canvas>

        </div>

    </section>


    {{-- =========================================================
         TABLA
    ========================================================== --}}

    <section class="history-card">

        <div class="section-header">

            <div>

                <h2>
                    Registros paginados
                </h2>

                <p>
                    25 mediciones por página. La gráfica y las estadísticas abarcan el período completo.
                </p>

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

            <strong>
                No se encontraron registros
            </strong>

            <span>
                No existen mediciones para los filtros seleccionados.
            </span>

        </div>


        <div
            id="history-table-wrapper"
            class="table-wrapper"
        >

            <table class="history-table">

                <thead>

                    <tr>

                        <th>
                            Fecha y hora
                        </th>

                        <th>
                            Valor
                        </th>

                        <th>
                            Sensor
                        </th>

                        <th>
                            Zona
                        </th>

                        <th>
                            Invernadero
                        </th>

                        <th>
                            Fuente
                        </th>

                    </tr>

                </thead>


                <tbody id="history-table-body"></tbody>

            </table>

        </div>

    </section>
    <nav class="history-pagination" aria-label="Paginación de lecturas">
        <p id="history-pagination" role="status" aria-live="polite"></p>
        <div class="history-page-buttons">
            <button type="button" id="history-previous" disabled>Página anterior</button>
            <button type="button" id="history-next" disabled>Página siguiente</button>
        </div>
    </nav>

    @include('history.irrigations')

</div>

@endsection


{{-- =============================================================
     ESTILOS
============================================================= --}}

@push('styles')

<style>

    .history-page {
        width: 100%;
    }


    .history-card {
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


    /* =========================================================
       FILTROS
    ========================================================== */

    .filters-grid {
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


    .filter-action {
        justify-content: flex-end;
    }


    .filter-action .primary-button {
        margin-top: auto;
    }


    .primary-button {
        min-height: 40px;

        padding: 9px 15px;

        border: 0;
        border-radius: 8px;

        background: #176136;

        color: #ffffff;

        cursor: pointer;

        font-size: 10px;
        font-weight: 700;
    }


    .primary-button:hover {
        background: #104c2a;
    }


    .primary-button:disabled {
        opacity: .55;

        cursor: not-allowed;
    }


    /* =========================================================
       RESUMEN
    ========================================================== */

    .summary-grid {
        display: grid;

        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 14px;

        margin-bottom: 20px;
    }


    .summary-card {
        min-height: 105px;

        padding: 18px;

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 13px;
    }


    .summary-label {
        display: block;

        color: #748078;

        font-size: 10px;
    }


    .summary-card strong {
        display: block;

        margin-top: 9px;

        color: #173d27;

        font-size: 25px;
    }


    .summary-card small {
        display: block;

        margin-top: 4px;

        color: #8a948e;

        font-size: 9px;
    }


    /* =========================================================
       GRÁFICA
    ========================================================== */

    .chart-container {
        position: relative;

        width: 100%;
        min-height: 280px;
        height: auto;
    }


    #history-chart {
        width: 100%;
        height: 100%;

        display: block;
    }


    /* =========================================================
       TABLA
    ========================================================== */

    .table-wrapper {
        width: 100%;

        overflow-x: auto;
    }


    .history-table {
        width: 100%;

        border-collapse: collapse;
    }


    .history-table th {
        padding: 11px 12px;

        border-bottom: 1px solid #dce5de;

        color: #617069;

        text-align: left;

        font-size: 9px;
        font-weight: 700;

        white-space: nowrap;
    }


    .history-table td {
        padding: 12px;

        border-bottom: 1px solid #eef2ef;

        color: #394940;

        font-size: 10px;

        vertical-align: middle;
    }


    .history-table tbody tr:hover {
        background: #f8faf8;
    }


    .table-primary {
        color: #294336;

        font-weight: 700;
    }


    .table-secondary {
        display: block;

        margin-top: 3px;

        color: #879088;

        font-size: 8px;
    }


    .source-badge {
        display: inline-flex;

        padding: 5px 8px;

        border-radius: 20px;

        background: #eef3ef;

        color: #526159;

        font-size: 8px;
        font-weight: 700;
    }


    /* =========================================================
       ESTADOS
    ========================================================== */

    .empty-state,
    .loading-state {
        padding: 35px 15px;

        text-align: center;

        color: #758079;
    }


    .empty-state strong {
        display: block;

        color: #425249;

        font-size: 12px;
    }


    .empty-state span {
        display: block;

        margin-top: 5px;

        font-size: 9px;
    }


    .loading-state {
        font-size: 10px;
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

    .history-page .form-group { min-width: 0; }
    .history-page .form-group label { font-size: 12px; }
    .history-page .form-group input,
    .history-page .form-group select { min-height: 44px; font-size: 13px; }
    .history-filter-actions {
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; margin-top: 20px; padding-top: 18px; border-top: 1px solid #e5ece7;
    }
    .history-filter-actions p { margin: 0; color: #637269; font-size: 12px; }
    .history-pagination {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
        gap: 16px; padding: 18px 22px; background: #fff;
        border: 1px solid #dce5de; border-radius: 12px;
    }
    .history-pagination p { flex: 1 1 240px; margin: 0; color: #55645b; font-size: 12px; line-height: 1.6; }
    .history-page-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
    .history-page-buttons button {
        min-height: 44px; padding: 10px 16px; background: #fff; border: 1px solid #176136;
        border-radius: 8px; color: #176136; font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .history-page-buttons button:hover:not(:disabled) { background: #edf5ef; }
    .history-page-buttons button:disabled { color: #7c8880; border-color: #dce5de; background: #f5f7f5; cursor: default; }
    .history-page button:focus-visible { outline: 3px solid #8ebd9c; outline-offset: 3px; }
    .history-page .summary-card strong { overflow-wrap: anywhere; }
    @media (max-width: 650px) {
        .history-filter-actions { align-items: stretch; flex-direction: column; }
        .history-pagination { padding: 16px; }
        .history-page-buttons { width: 100%; }
        .history-page-buttons button { flex: 1 1 120px; }
    }

    @media (max-width: 1050px) {

        .filters-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }


    @media (max-width: 650px) {

        .filters-grid,
        .summary-grid {
            grid-template-columns: 1fr;
        }


        .chart-container {
            height: auto;
        }

    }

</style>

@endpush


{{-- =============================================================
     JAVASCRIPT
============================================================= --}}

@push('scripts')

<script src="{{ asset('js/measurement-history.js') }}"></script>
<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        let historyPage = 1;
        let historyLastPage = 1;
        document.getElementById('history-previous').addEventListener('click', () => { if (historyPage > 1) { historyPage--; loadHistory(); } });
        document.getElementById('history-next').addEventListener('click', () => { if (historyPage < historyLastPage) { historyPage++; loadHistory(); } });
        document.getElementById('history-unit').addEventListener('change', () => { historyPage = 1; loadHistory(); });
        const initialHistoryFilters = new URLSearchParams(window.location.search);
        document.getElementById('history-sensor').value = initialHistoryFilters.get('sensor_id') ?? '';
        if (initialHistoryFilters.has('metric')) document.getElementById('history-metric').value = initialHistoryFilters.get('metric');
        const historyToken =
            sessionStorage.getItem(
                'lumatek_access_token'
            );


        if (!historyToken) {

            window.location.href =
                '/login';

            return;
        }


        const greenhouseSelect =
            document.getElementById(
                'history-greenhouse'
            );


        const zoneSelect =
            document.getElementById(
                'history-zone'
            );


        const metricSelect =
            document.getElementById(
                'history-metric'
            );


        const startDateInput =
            document.getElementById(
                'history-start-date'
            );


        const endDateInput =
            document.getElementById(
                'history-end-date'
            );


        const filterForm =
            document.getElementById(
                'history-filter-form'
            );


        const searchButton =
            document.getElementById(
                'history-search-button'
            );


        const loading =
            document.getElementById(
                'history-loading'
            );


        const empty =
            document.getElementById(
                'history-empty'
            );


        const tableWrapper =
            document.getElementById(
                'history-table-wrapper'
            );


        const tableBody =
            document.getElementById(
                'history-table-body'
            );


        const chart =
            document.getElementById(
                'history-chart'
            );


        const chartContainer =
            document.getElementById(
                'chart-container'
            );


        const chartEmpty =
            document.getElementById(
                'chart-empty'
            );


        let currentHistoryData = [];
        let currentChartData = [];
        let currentChartPeriod = {};
        let currentMetric = {
            label: 'Temperatura',
            unit: '°C',
        };


        /*
        |--------------------------------------------------------------------------
        | FECHAS INICIALES
        |--------------------------------------------------------------------------
        */

        function initializeDates() {

            const today =
                new Date();


            const sevenDaysAgo =
                new Date();


            sevenDaysAgo.setDate(
                today.getDate() - 7
            );


            startDateInput.value =
                formatInputDate(
                    sevenDaysAgo
                );


            endDateInput.value =
                formatInputDate(
                    today
                );
        }


        function formatInputDate(
            date
        ) {

            const year =
                date.getFullYear();


            const month =
                String(
                    date.getMonth() + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    date.getDate()
                ).padStart(
                    2,
                    '0'
                );


            return `${year}-${month}-${day}`;
        }


        /*
        |--------------------------------------------------------------------------
        | AUTENTICACIÓN
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | MENSAJES
        |--------------------------------------------------------------------------
        */

        function showMessage(
            message,
            type = 'error'
        ) {

            const box =
                document.getElementById(
                    'history-message'
                );


            box.textContent =
                message;


            box.className =
                'message-box ' +
                (
                    type === 'success'
                        ? 'message-success'
                        : 'message-error'
                );


            box.style.display =
                'block';
        }


        function hideMessage() {

            const box =
                document.getElementById(
                    'history-message'
                );


            box.style.display =
                'none';
        }


        /*
        |--------------------------------------------------------------------------
        | INVERNADEROS
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
                                    `Bearer ${historyToken}`,
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
                    Array.isArray(
                        result.data
                    )
                        ? result.data
                        : [];


                greenhouseSelect.innerHTML =
                    `
                        <option value="">
                            Todos los invernaderos
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
                    'No fue posible cargar los invernaderos.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | ZONAS
        |--------------------------------------------------------------------------
        */

        async function loadZones(
            greenhouseId
        ) {

            zoneSelect.innerHTML =
                `
                    <option value="">
                        Todas las zonas
                    </option>
                `;


            if (!greenhouseId) {

                zoneSelect.disabled =
                    true;


                return;
            }


            zoneSelect.disabled =
                true;


            try {

                const response =
                    await fetch(
                        `/api/greenhouses/${greenhouseId}/zones`,
                        {
                            headers: {

                                'Accept':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${historyToken}`,
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
                    Array.isArray(
                        result.data
                    )
                        ? result.data
                        : [];


                zones.forEach(
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

                showMessage(
                    'No fue posible cargar las zonas.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | HISTORIAL
        |--------------------------------------------------------------------------
        */

        async function loadHistory() {

            hideMessage();


            loading.style.display =
                'block';


            empty.style.display =
                'none';


            tableWrapper.style.display =
                'none';


            chartContainer.style.display =
                'none';


            chartEmpty.style.display =
                'none';


            searchButton.disabled =
                true;


            searchButton.textContent =
                'Consultando...';


            const params =
                new URLSearchParams();


            params.append(
                'metric',
                metricSelect.value
            );


            if (
                greenhouseSelect.value
            ) {

                params.append(
                    'greenhouse_id',
                    greenhouseSelect.value
                );
            }


            if (
                zoneSelect.value
            ) {

                params.append(
                    'zone_id',
                    zoneSelect.value
                );
            }


            if (
                startDateInput.value
            ) {

                params.append(
                    'start_date',
                    startDateInput.value
                );
            }


            if (
                endDateInput.value
            ) {

                params.append(
                    'end_date',
                    endDateInput.value
                );
            }


            if (document.getElementById('history-sensor').value) params.set('sensor_id', document.getElementById('history-sensor').value);
            params.set('page', historyPage);
            params.set('per_page', 25);
            const requestedUnit = document.getElementById('history-unit').value;
            if (requestedUnit) params.set('unit', requestedUnit);
            try {

                const response =
                    await fetch(
                        `/api/history?${params.toString()}`,
                        {
                            headers: {

                                'Accept':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${historyToken}`,
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
                        ?? 'No fue posible consultar el historial.'
                    );


                    return;
                }


                currentHistoryData =
                    result.data ?? [];
                historyLastPage = result.pagination?.last_page ?? 1;
                historyPage = result.pagination?.page ?? historyPage;
                document.getElementById('history-pagination').textContent = window.LumaMeasurementHistory.paginationText(result.pagination);
                document.getElementById('history-previous').disabled = historyPage <= 1;
                document.getElementById('history-next').disabled = historyPage >= historyLastPage;



                currentMetric =
                    result.metric ?? {
                        label: 'Medición',
                        unit: '',
                    };


                renderSummary(
                    result.summary
                );


                updateChartTitle();


                renderTable(
                    currentHistoryData
                );


                currentChartData = result.chart?.data ?? [];
                currentChartPeriod = result.chart ?? {};
                document.getElementById('chart-subtitle').textContent = result.chart?.reduced
                    ? 'Gráfica del período completo: muestras reales reducidas (primera, última, mínima y máxima por intervalo). Puntos sin unir para conservar los cortes.'
                    : 'Gráfica del período completo: mediciones detalladas, independiente de la tabla.';
                drawChart(currentChartData);


            } catch (error) {

                showMessage(
                    'No fue posible conectar con el servidor.'
                );


            } finally {

                loading.style.display =
                    'none';


                searchButton.disabled =
                    false;


                searchButton.textContent =
                    'Consultar historial';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RESUMEN
        |--------------------------------------------------------------------------
        */

        function renderSummary(
            summary
        ) {

            const unit =
                currentMetric.unit
                ?? '';


            document.getElementById(
                'summary-total'
            ).textContent =
                summary?.total_readings
                ?? 0;


            document.getElementById(
                'summary-minimum'
            ).textContent =
                window.LumaMeasurementHistory.metricText(summary, 'minimum');


            document.getElementById(
                'summary-maximum'
            ).textContent =
                window.LumaMeasurementHistory.metricText(summary, 'maximum');


            document.getElementById(
                'summary-average'
            ).textContent =
                window.LumaMeasurementHistory.metricText(summary, 'average');


            document.getElementById(
                'summary-minimum-unit'
            ).textContent =
                unit || '—';


            document.getElementById(
                'summary-maximum-unit'
            ).textContent =
                unit || '—';


            document.getElementById(
                'summary-average-unit'
            ).textContent =
                unit || '—';
        }


        function formatSummaryValue(
            value
        ) {

            if (
                value === null
                ||
                value === undefined
            ) {

                return '—';
            }


            return Number(
                value
            ).toFixed(2);
        }


        /*
        |--------------------------------------------------------------------------
        | TABLA
        |--------------------------------------------------------------------------
        */

        function renderTable(
            readings
        ) {

            tableBody.innerHTML =
                '';


            if (!readings.length) {

                empty.style.display =
                    'block';


                tableWrapper.style.display =
                    'none';


                return;
            }


            empty.style.display =
                'none';


            tableWrapper.style.display =
                'block';


            const descending =
                [...readings].reverse();


            descending.forEach(
                reading => {

                    const row =
                        document.createElement(
                            'tr'
                        );


                    row.innerHTML = `

                        <td>

                            <span class="table-primary">

                                ${escapeHtml(
                                    formatDateTime(
                                        reading.recorded_at
                                    )
                                )}

                            </span>

                        </td>


                        <td>

                            <span class="table-primary">

                                ${Number(
                                    reading.value
                                ).toFixed(2)}

                                ${escapeHtml(
                                    reading.unit ?? reading.sensor?.unit ?? 'unknown'
                                )}

                            </span>

                        </td>


                        <td>

                            <span class="table-primary">

                                ${escapeHtml(
                                    reading.sensor?.name
                                    ?? 'Sin sensor'
                                )}

                            </span>

                            <span class="table-secondary">

                                ${escapeHtml(
                                    reading.sensor?.code
                                    ?? ''
                                )}

                            </span>

                        </td>


                        <td>

                            ${escapeHtml(
                                reading.zone?.name
                                ?? '—'
                            )}

                        </td>


                        <td>

                            ${escapeHtml(
                                reading.greenhouse?.name
                                ?? '—'
                            )}

                        </td>


                        <td>

                            <span class="source-badge">

                                ${formatSource(
                                    reading
                                )}<small>Recepción: ${escapeHtml(reading.received_at ?? 'Sin fecha acreditada')} - ${reading.metadata_origin === 'legacy_schema' ? 'Unidad inferida del esquema anterior' : 'Metadatos capturados'}</small>

                            </span>

                        </td>
                    `;


                    tableBody.appendChild(
                        row
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GRÁFICA
        |--------------------------------------------------------------------------
        */

        function updateChartTitle() {

            document.getElementById(
                'chart-title'
            ).textContent =
                `Evolución de ${currentMetric.label.toLowerCase()}`;
        }


        function drawChart(readings) {
            chartContainer.style.display = readings.length ? 'block' : 'none';
            chartEmpty.style.display = readings.length ? 'none' : 'block';
            if (!readings.length) return;
            chartContainer.style.height = "auto";
            window.LumaMeasurementHistory.draw(chart, readings, chartContainer.clientWidth, 300, currentChartPeriod);
        }

        function drawGrid(
            context,
            padding,
            width,
            height,
            minimum,
            maximum
        ) {

            const divisions = 5;


            context.font =
                '10px Arial';


            context.textAlign =
                'right';


            context.textBaseline =
                'middle';


            for (
                let index = 0;
                index <= divisions;
                index++
            ) {

                const y =
                    padding.top
                    + (
                        height
                        / divisions
                        * index
                    );


                const value =
                    maximum
                    - (
                        (
                            maximum
                            - minimum
                        )
                        / divisions
                        * index
                    );


                context.beginPath();


                context.strokeStyle =
                    '#e5ebe7';


                context.lineWidth =
                    1;


                context.moveTo(
                    padding.left,
                    y
                );


                context.lineTo(
                    padding.left
                    + width,
                    y
                );


                context.stroke();


                context.fillStyle =
                    '#76827a';


                context.fillText(
                    value.toFixed(
                        1
                    ),
                    padding.left - 8,
                    y
                );
            }
        }


        function drawLine(
            context,
            readings,
            padding,
            width,
            height,
            minimum,
            maximum
        ) {

            const count =
                readings.length;


            context.beginPath();


            context.strokeStyle =
                '#176136';


            context.lineWidth =
                2;


            readings.forEach(
                (
                    reading,
                    index
                ) => {

                    const x =
                        count === 1
                            ? padding.left
                                + width / 2
                            : padding.left
                                + (
                                    index
                                    / (
                                        count - 1
                                    )
                                )
                                * width;


                    const value =
                        Number(
                            reading.value
                        );


                    const normalized =
                        (
                            value
                            - minimum
                        )
                        /
                        (
                            maximum
                            - minimum
                        );


                    const y =
                        padding.top
                        + height
                        - (
                            normalized
                            * height
                        );


                    if (index === 0) {

                        context.moveTo(
                            x,
                            y
                        );

                    } else {

                        context.lineTo(
                            x,
                            y
                        );
                    }
                }
            );


            context.stroke();


            readings.forEach(
                (
                    reading,
                    index
                ) => {

                    const x =
                        count === 1
                            ? padding.left
                                + width / 2
                            : padding.left
                                + (
                                    index
                                    / (
                                        count - 1
                                    )
                                )
                                * width;


                    const value =
                        Number(
                            reading.value
                        );


                    const normalized =
                        (
                            value
                            - minimum
                        )
                        /
                        (
                            maximum
                            - minimum
                        );


                    const y =
                        padding.top
                        + height
                        - (
                            normalized
                            * height
                        );


                    context.beginPath();


                    context.fillStyle =
                        '#176136';


                    context.arc(
                        x,
                        y,
                        4,
                        0,
                        Math.PI * 2
                    );


                    context.fill();
                }
            );
        }


        function drawDateLabels(
            context,
            readings,
            padding,
            width,
            height
        ) {

            if (!readings.length) {

                return;
            }


            const indexes = [];


            if (
                readings.length <= 4
            ) {

                readings.forEach(
                    (
                        reading,
                        index
                    ) => {

                        indexes.push(
                            index
                        );
                    }
                );

            } else {

                indexes.push(
                    0
                );


                indexes.push(
                    Math.floor(
                        (
                            readings.length
                            - 1
                        )
                        / 3
                    )
                );


                indexes.push(
                    Math.floor(
                        (
                            readings.length
                            - 1
                        )
                        * 2
                        / 3
                    )
                );


                indexes.push(
                    readings.length - 1
                );
            }


            context.font =
                '9px Arial';


            context.fillStyle =
                '#76827a';


            context.textAlign =
                'center';


            context.textBaseline =
                'top';


            indexes.forEach(
                index => {

                    const x =
                        readings.length === 1
                            ? padding.left
                                + width / 2
                            : padding.left
                                + (
                                    index
                                    / (
                                        readings.length
                                        - 1
                                    )
                                )
                                * width;


                    const y =
                        padding.top
                        + height
                        + 12;


                    context.fillText(
                        formatShortDate(
                            readings[index]
                                .recorded_at
                        ),
                        x,
                        y
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FORMATOS
        |--------------------------------------------------------------------------
        */

        function formatDateTime(
            value
        ) {

            if (!value) {

                return '—';
            }


            const normalized =
                value.replace(
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


        function formatShortDate(
            value
        ) {

            if (!value) {

                return '';
            }


            const normalized =
                value.replace(
                    ' ',
                    'T'
                );


            const date =
                new Date(
                    normalized
                );


            return date.toLocaleDateString(
                'es-MX',
                {
                    day:
                        '2-digit',

                    month:
                        'short',
                }
            );
        }


        function formatSource(reading) {
            return reading?.source_label ?? 'Origen no verificado';
        }


        function escapeHtml(
            value
        ) {

            const element =
                document.createElement(
                    'div'
                );


            element.textContent =
                value ?? '';


            return element.innerHTML;
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


        filterForm.addEventListener(
            'submit',
            function (
                event
            ) {

                event.preventDefault();
                historyPage = 1;


                loadHistory();
            }
        );


        window.addEventListener(
            'resize',
            function () {

                if (
                    currentChartData.length
                ) {

                    drawChart(
                        currentChartData
                    );
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | INICIALIZACIÓN
        |--------------------------------------------------------------------------
        */

        initializeDates();

        loadGreenhouses();

        loadHistory();

    }
);

</script>

@endpush