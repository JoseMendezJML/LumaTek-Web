@extends('layouts.app')

@section('title', 'Alertas | LumaTek')

@section('page-title', 'Centro de alertas')

@section(
    'page-subtitle',
    'Consulta y atiende las alertas generadas por los sensores'
)

@section('content')

<div class="alerts-page">

    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <section class="alerts-summary-grid">

        <article class="summary-card">

            <span class="summary-label">
                Total
            </span>

            <strong
                id="summary-total"
                class="summary-value"
            >
                0
            </strong>

        </article>


        <article class="summary-card summary-active">

            <span class="summary-label">
                Activas
            </span>

            <strong
                id="summary-active"
                class="summary-value"
            >
                0
            </strong>

        </article>


        <article class="summary-card summary-acknowledged">

            <span class="summary-label">
                Atendidas
            </span>

            <strong
                id="summary-acknowledged"
                class="summary-value"
            >
                0
            </strong>

        </article>


        <article class="summary-card summary-resolved">

            <span class="summary-label">
                Resueltas
            </span>

            <strong
                id="summary-resolved"
                class="summary-value"
            >
                0
            </strong>

        </article>

    </section>


    {{-- =========================================================
         FILTROS
    ========================================================== --}}

    <section
        class="panel-card"
        style="margin-top: 24px;"
    >

        <div class="panel-card-header">

            <div>

                <h2>
                    Filtros
                </h2>

                <p>
                    Filtra las alertas por invernadero,
                    estado o severidad.
                </p>

            </div>

            <button
                type="button"
                id="clear-filters"
                class="btn-secondary"
            >
                Limpiar filtros
            </button>

        </div>


        <div class="panel-card-body">

            <div class="alerts-filter-grid">


                <div class="panel-form-group">

                    <label for="filter-greenhouse">
                        Invernadero
                    </label>

                    <select id="filter-greenhouse">

                        <option value="">
                            Todos
                        </option>

                    </select>

                </div>


                <div class="panel-form-group">

                    <label for="filter-status">
                        Estado
                    </label>

                    <select id="filter-status">

                        <option value="">
                            Todos
                        </option>

                        <option value="active">
                            Activa
                        </option>

                        <option value="acknowledged">
                            Atendida
                        </option>

                        <option value="resolved">
                            Resuelta
                        </option>

                    </select>

                </div>


                <div class="panel-form-group">

                    <label for="filter-severity">
                        Severidad
                    </label>

                    <select id="filter-severity">

                        <option value="">
                            Todas
                        </option>

                        <option value="info">
                            Informativa
                        </option>

                        <option value="warning">
                            Advertencia
                        </option>

                        <option value="critical">
                            Crítica
                        </option>

                    </select>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         ALERTAS
    ========================================================== --}}

    <section
        class="panel-card"
        style="margin-top: 24px;"
    >

        <div class="panel-card-header">

            <div>

                <h2>
                    Alertas registradas
                </h2>

                <p>
                    Historial de incidencias detectadas
                    por los sensores.
                </p>

            </div>

            <span id="alerts-count">
                0 alertas
            </span>

        </div>


        <div class="panel-card-body">

            <div
                id="alert-message"
                class="panel-message"
                style="display: none;"
            ></div>


            <div
                id="alerts-loading"
                class="alerts-state"
            >
                Cargando alertas...
            </div>


            <div
                id="alerts-empty"
                class="alerts-state"
                style="display: none;"
            >

                <div class="alerts-empty-icon">
                    ✅
                </div>

                <strong>
                    No se encontraron alertas
                </strong>

                <span>
                    No hay registros que coincidan
                    con los filtros seleccionados.
                </span>

            </div>


            <div
                id="alerts-list"
                class="alerts-list"
            ></div>

        </div>

    </section>

</div>

@endsection


@push('styles')

<style>

    .alerts-page {
        width: 100%;
    }


    /* =========================================================
       RESUMEN
    ========================================================== */

    .alerts-summary-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .summary-card {
        padding: 18px;
        background: #ffffff;
        border: 1px solid #e1e9e3;
        border-radius: 13px;
    }

    .summary-label {
        display: block;
        color: #748078;
        font-size: 11px;
        font-weight: 600;
    }

    .summary-value {
        display: block;
        margin-top: 7px;
        color: #173d27;
        font-size: 29px;
    }

    .summary-active {
        border-left: 4px solid #b53b3b;
    }

    .summary-acknowledged {
        border-left: 4px solid #c2852d;
    }

    .summary-resolved {
        border-left: 4px solid #2d7b4a;
    }


    /* =========================================================
       FILTROS
    ========================================================== */

    .alerts-filter-grid {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .alerts-page select {
        width: 100%;
        min-height: 44px;
        padding: 0 12px;
        border: 1px solid #d9e3dc;
        border-radius: 9px;
        background: #ffffff;
        color: #293d30;
    }


    /* =========================================================
       ESTADOS
    ========================================================== */

    .alerts-state {
        text-align: center;
        padding: 40px 20px;
        color: #768178;
    }

    .alerts-state strong {
        display: block;
        margin-top: 8px;
        color: #415147;
        font-size: 14px;
    }

    .alerts-state span {
        display: block;
        margin-top: 4px;
        font-size: 11px;
    }

    .alerts-empty-icon {
        font-size: 30px;
    }


    /* =========================================================
       LISTADO
    ========================================================== */

    .alerts-list {
        display: grid;
        gap: 14px;
    }

    .alert-card {
        padding: 17px;
        border: 1px solid #e0e8e2;
        border-radius: 12px;
        background: #ffffff;
    }

    .alert-card.active {
        border-left: 4px solid #bb4141;
    }

    .alert-card.acknowledged {
        border-left: 4px solid #c68a33;
    }

    .alert-card.resolved {
        border-left: 4px solid #3f875a;
        opacity: .85;
    }


    /* =========================================================
       HEADER ALERTA
    ========================================================== */

    .alert-header {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        align-items: flex-start;
    }

    .alert-title {
        margin: 0;
        color: #173d27;
        font-size: 15px;
    }

    .alert-date {
        display: block;
        margin-top: 4px;
        color: #7b867e;
        font-size: 10px;
    }

    .alert-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .alert-badge {
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
    }

    .badge-active {
        background: #fff0f0;
        color: #a62c2c;
    }

    .badge-acknowledged {
        background: #fff7e8;
        color: #94651f;
    }

    .badge-resolved {
        background: #eaf7ee;
        color: #176136;
    }

    .badge-info {
        background: #eef4fb;
        color: #356798;
    }

    .badge-warning {
        background: #fff3e4;
        color: #995d1f;
    }

    .badge-critical {
        background: #fde9e9;
        color: #a11f1f;
    }


    /* =========================================================
       MENSAJE
    ========================================================== */

    .alert-message {
        margin: 14px 0;
        padding: 11px 13px;
        border-radius: 9px;
        background: #f8faf8;
        color: #405146;
        font-size: 11px;
        line-height: 1.5;
    }


    /* =========================================================
       INFORMACIÓN
    ========================================================== */

    .alert-info-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .alert-info {
        padding: 10px;
        background: #f7faf8;
        border-radius: 8px;
    }

    .alert-info span {
        display: block;
        color: #7c8780;
        font-size: 9px;
    }

    .alert-info strong {
        display: block;
        margin-top: 4px;
        color: #34483b;
        font-size: 11px;
        overflow-wrap: anywhere;
    }


    /* =========================================================
       LECTURA
    ========================================================== */

    .alert-reading {
        margin-top: 14px;
        padding: 11px 13px;
        background: #f1f8f3;
        border-radius: 9px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
    }

    .alert-reading span {
        color: #708076;
        font-size: 10px;
    }

    .alert-reading strong {
        color: #176136;
        font-size: 17px;
    }


    /* =========================================================
       ATENDIDA POR
    ========================================================== */

    .alert-attended {
        margin-top: 10px;
        color: #778279;
        font-size: 10px;
    }


    /* =========================================================
       BOTONES
    ========================================================== */

    .alert-actions {
        display: flex;
        gap: 8px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .alert-action {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #d8e3da;
        background: #ffffff;
        cursor: pointer;
        color: #385046;
        font-size: 10px;
        font-weight: 700;
    }

    .alert-action:hover {
        background: #f4f8f5;
    }

    .alert-action-acknowledge {
        border-color: #e7cc9e;
        color: #94611e;
    }

    .alert-action-resolve {
        border-color: #bcdcc5;
        color: #176136;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 1000px) {

        .alerts-summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .alert-info-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 700px) {

        .alerts-filter-grid,
        .alerts-summary-grid,
        .alert-info-grid {
            grid-template-columns: 1fr;
        }

        .alert-header {
            flex-direction: column;
        }

        .alert-badges {
            justify-content: flex-start;
        }

    }

</style>

@endpush


@push('scripts')

<script>

    const token =
        sessionStorage.getItem(
            'lumatek_access_token'
        );


    if (!token) {

        window.location.href =
            '/login';
    }


    const summaryTotal =
        document.getElementById(
            'summary-total'
        );

    const summaryActive =
        document.getElementById(
            'summary-active'
        );

    const summaryAcknowledged =
        document.getElementById(
            'summary-acknowledged'
        );

    const summaryResolved =
        document.getElementById(
            'summary-resolved'
        );


    const filterGreenhouse =
        document.getElementById(
            'filter-greenhouse'
        );

    const filterStatus =
        document.getElementById(
            'filter-status'
        );

    const filterSeverity =
        document.getElementById(
            'filter-severity'
        );

    const clearFilters =
        document.getElementById(
            'clear-filters'
        );


    const alertsList =
        document.getElementById(
            'alerts-list'
        );

    const alertsLoading =
        document.getElementById(
            'alerts-loading'
        );

    const alertsEmpty =
        document.getElementById(
            'alerts-empty'
        );

    const alertsCount =
        document.getElementById(
            'alerts-count'
        );

    const alertMessage =
        document.getElementById(
            'alert-message'
        );


    /*
    |--------------------------------------------------------------------------
    | UTILIDADES
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }


    function logoutIfUnauthorized(
        response
    ) {

        if (response.status === 401) {

            sessionStorage.clear();

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

        alertMessage.className =
            'panel-message ' +
            (
                type === 'success'
                    ? 'panel-message-success'
                    : 'panel-message-error'
            );

        alertMessage.textContent =
            text;

        alertMessage.style.display =
            'block';
    }


    function hideMessage() {

        alertMessage.style.display =
            'none';
    }


    function formatDate(
        value
    ) {

        if (!value) {
            return 'Sin fecha';
        }


        const date =
            new Date(
                value.replace(
                    ' ',
                    'T'
                )
            );


        return date.toLocaleString(
            'es-MX',
            {
                dateStyle:
                    'short',

                timeStyle:
                    'short'
            }
        );
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
                                `Bearer ${token}`
                        }
                    }
                );


            if (
                logoutIfUnauthorized(
                    response
                )
            ) {
                return;
            }


            const result =
                await response.json();


            const greenhouses =
                result.data ?? [];


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


                    filterGreenhouse.appendChild(
                        option
                    );

                }
            );


        } catch (error) {

            console.error(
                'No fue posible cargar los invernaderos.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR ALERTAS
    |--------------------------------------------------------------------------
    */

    async function loadAlerts() {

        hideMessage();


        alertsLoading.style.display =
            'block';


        alertsEmpty.style.display =
            'none';


        alertsList.innerHTML =
            '';


        const params =
            new URLSearchParams();


        if (filterGreenhouse.value) {

            params.append(
                'greenhouse_id',
                filterGreenhouse.value
            );
        }


        if (filterStatus.value) {

            params.append(
                'status',
                filterStatus.value
            );
        }


        if (filterSeverity.value) {

            params.append(
                'severity',
                filterSeverity.value
            );
        }


        let url =
            '/api/alerts';


        if (params.toString()) {

            url +=
                '?' + params.toString();
        }


        try {

            const response =
                await fetch(
                    url,
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (
                logoutIfUnauthorized(
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
                    ?? 'No fue posible cargar las alertas.',
                    'error'
                );

                return;
            }


            const summary =
                result.summary ?? {};


            const alerts =
                result.data ?? [];


            summaryTotal.textContent =
                summary.total ?? 0;


            summaryActive.textContent =
                summary.active ?? 0;


            summaryAcknowledged.textContent =
                summary.acknowledged ?? 0;


            summaryResolved.textContent =
                summary.resolved ?? 0;


            alertsCount.textContent =
                `${alerts.length} alerta${alerts.length === 1 ? '' : 's'}`;


            alertsLoading.style.display =
                'none';


            if (!alerts.length) {

                alertsEmpty.style.display =
                    'block';

                return;
            }


            alerts.forEach(
                alert => {

                    createAlertCard(
                        alert
                    );

                }
            );


        } catch (error) {

            alertsLoading.style.display =
                'none';


            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TARJETA
    |--------------------------------------------------------------------------
    */

    function createAlertCard(
        alert
    ) {

        const card =
            document.createElement(
                'article'
            );


        card.className =
            `alert-card ${alert.status}`;


        const reading =
            alert.reading;


        const readingHtml =
            reading
                ? `
                    <div class="alert-reading">

                        <span>
                            Lectura que generó la alerta
                        </span>

                        <strong>
                            ${Number(
                                reading.value
                            ).toFixed(1)}
                            ${escapeHtml(
                                alert.sensor?.unit
                                ?? ''
                            )}
                        </strong>

                    </div>
                `
                : '';


        const attendedHtml =
            alert.acknowledged_by
                ? `
                    <div class="alert-attended">

                        Atendida por
                        <strong>
                            ${escapeHtml(
                                alert.acknowledged_by.name
                            )}
                        </strong>

                        ${alert.acknowledged_at
                            ? ' · ' +
                              escapeHtml(
                                  formatDate(
                                      alert.acknowledged_at
                                  )
                              )
                            : ''
                        }

                    </div>
                `
                : '';


        let actionsHtml =
            '';


        if (
            alert.status ===
            'active'
        ) {

            actionsHtml = `

                <button
                    type="button"
                    class="alert-action alert-action-acknowledge"
                    data-action="acknowledge"
                >
                    Marcar atendida
                </button>

                <button
                    type="button"
                    class="alert-action alert-action-resolve"
                    data-action="resolve"
                >
                    Resolver
                </button>
            `;
        }


        else if (
            alert.status ===
            'acknowledged'
        ) {

            actionsHtml = `

                <button
                    type="button"
                    class="alert-action alert-action-resolve"
                    data-action="resolve"
                >
                    Resolver
                </button>
            `;
        }


        card.innerHTML = `

            <div class="alert-header">

                <div>

                    <h3 class="alert-title">
                        ${escapeHtml(
                            alert.type_label
                        )}
                    </h3>

                    <span class="alert-date">
                        ${escapeHtml(
                            formatDate(
                                alert.created_at
                            )
                        )}
                    </span>

                </div>


                <div class="alert-badges">

                    <span
                        class="
                            alert-badge
                            badge-${escapeHtml(
                                alert.status
                            )}
                        "
                    >
                        ${escapeHtml(
                            alert.status_label
                        )}
                    </span>


                    <span
                        class="
                            alert-badge
                            badge-${escapeHtml(
                                alert.severity
                            )}
                        "
                    >
                        ${escapeHtml(
                            alert.severity_label
                        )}
                    </span>

                </div>

            </div>


            <div class="alert-message">

                ${escapeHtml(
                    alert.message
                )}

            </div>


            <div class="alert-info-grid">


                <div class="alert-info">

                    <span>
                        Invernadero
                    </span>

                    <strong>
                        ${escapeHtml(
                            alert.greenhouse?.name
                            ?? 'Sin información'
                        )}
                    </strong>

                </div>


                <div class="alert-info">

                    <span>
                        Zona
                    </span>

                    <strong>
                        ${escapeHtml(
                            alert.zone?.name
                            ?? 'Sin información'
                        )}
                    </strong>

                </div>


                <div class="alert-info">

                    <span>
                        Dispositivo
                    </span>

                    <strong>
                        ${escapeHtml(
                            alert.device?.name
                            ?? 'Sin información'
                        )}
                    </strong>

                </div>


                <div class="alert-info">

                    <span>
                        Sensor
                    </span>

                    <strong>
                        ${escapeHtml(
                            alert.sensor?.name
                            ?? 'Sin información'
                        )}
                    </strong>

                </div>

            </div>


            ${readingHtml}

            ${attendedHtml}


            ${
                actionsHtml
                    ? `
                        <div class="alert-actions">
                            ${actionsHtml}
                        </div>
                    `
                    : ''
            }
        `;


        const acknowledgeButton =
            card.querySelector(
                '[data-action="acknowledge"]'
            );


        if (acknowledgeButton) {

            acknowledgeButton
                .addEventListener(
                    'click',
                    () =>
                        acknowledgeAlert(
                            alert
                        )
                );
        }


        const resolveButton =
            card.querySelector(
                '[data-action="resolve"]'
            );


        if (resolveButton) {

            resolveButton
                .addEventListener(
                    'click',
                    () =>
                        resolveAlert(
                            alert
                        )
                );
        }


        alertsList.appendChild(
            card
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ATENDER ALERTA
    |--------------------------------------------------------------------------
    */

    async function acknowledgeAlert(
        alert
    ) {

        const confirmed =
            confirm(
                `¿Deseas marcar como atendida la alerta "${alert.type_label}"?`
            );


        if (!confirmed) {
            return;
        }


        await performAlertAction(
            alert.id,
            'acknowledge'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVER ALERTA
    |--------------------------------------------------------------------------
    */

    async function resolveAlert(
        alert
    ) {

        const confirmed =
            confirm(
                `¿Deseas resolver la alerta "${alert.type_label}"?`
            );


        if (!confirmed) {
            return;
        }


        await performAlertAction(
            alert.id,
            'resolve'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EJECUTAR ACCIÓN
    |--------------------------------------------------------------------------
    */

    async function performAlertAction(
        alertId,
        action
    ) {

        hideMessage();


        try {

            const response =
                await fetch(
                    `/api/alerts/${alertId}/${action}`,
                    {
                        method:
                            'PATCH',

                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            const result =
                await response.json();


            if (
                logoutIfUnauthorized(
                    response
                )
            ) {
                return;
            }


            if (!response.ok) {

                showMessage(
                    result.message
                    ?? 'No fue posible actualizar la alerta.',
                    'error'
                );

                return;
            }


            showMessage(
                result.message
            );


            await loadAlerts();


        } catch (error) {

            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FILTROS
    |--------------------------------------------------------------------------
    */

    filterGreenhouse.addEventListener(
        'change',
        loadAlerts
    );


    filterStatus.addEventListener(
        'change',
        loadAlerts
    );


    filterSeverity.addEventListener(
        'change',
        loadAlerts
    );


    clearFilters.addEventListener(
        'click',
        function () {

            filterGreenhouse.value =
                '';

            filterStatus.value =
                '';

            filterSeverity.value =
                '';


            loadAlerts();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    loadGreenhouses();

    loadAlerts();

</script>

@endpush