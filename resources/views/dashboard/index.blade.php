@extends('layouts.app')

@section('title', 'Dashboard | LumaTek')

@section('page-title', 'Dashboard')

@section(
    'page-subtitle',
    'Resumen general del monitoreo de tus invernaderos'
)

@section('content')

<div class="dashboard-page">

    {{-- =========================================================
         ENCABEZADO
    ========================================================== --}}

    <section class="dashboard-heading">

        <div>

            <h2>
                Estado general
            </h2>

            <p>
                Consulta el estado actual de tus invernaderos,
                sensores y alertas.
            </p>

        </div>


        <button
            type="button"
            id="refresh-dashboard"
            class="btn-secondary"
        >
            Actualizar datos
        </button>

    </section>


    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <section class="summary-grid">

        <article class="summary-card">

            <div class="summary-icon">
                🌱
            </div>

            <div>

                <span>
                    Invernaderos activos
                </span>

                <strong id="summary-greenhouses">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card">

            <div class="summary-icon">
                🗺️
            </div>

            <div>

                <span>
                    Zonas activas
                </span>

                <strong id="summary-zones">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card">

            <div class="summary-icon">
                📡
            </div>

            <div>

                <span>
                    Dispositivos activos
                </span>

                <strong id="summary-devices">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card">

            <div class="summary-icon">
                🌡️
            </div>

            <div>

                <span>
                    Sensores activos
                </span>

                <strong id="summary-sensors">
                    0
                </strong>

            </div>

        </article>


        <article class="summary-card summary-alert">

            <div class="summary-icon">
                🚨
            </div>

            <div>

                <span>
                    Alertas activas
                </span>

                <strong id="summary-alerts">
                    0
                </strong>

            </div>

        </article>

    </section>


    {{-- =========================================================
         ESTADO DE CARGA
    ========================================================== --}}

    <div
        id="dashboard-message"
        class="panel-message"
        style="display:none; margin-top:22px;"
    ></div>


    <div
        id="dashboard-loading"
        class="dashboard-state"
    >
        Cargando información del dashboard...
    </div>


    {{-- =========================================================
         INVERNADEROS
    ========================================================== --}}

    <section
        id="greenhouses-section"
        class="panel-card"
        style="margin-top:24px; display:none;"
    >

        <div class="panel-card-header">

            <div>

                <h2>
                    Monitoreo por invernadero
                </h2>

                <p>
                    Lecturas actuales de los sensores configurados.
                </p>

            </div>

            <span id="dashboard-greenhouses-count">
                0 invernaderos
            </span>

        </div>


        <div class="panel-card-body">

            <div
                id="dashboard-greenhouses-empty"
                class="dashboard-state"
                style="display:none;"
            >

                <div class="empty-icon">
                    🌱
                </div>

                <strong>
                    No hay invernaderos activos
                </strong>

                <span>
                    Registra un invernadero para comenzar el monitoreo.
                </span>

            </div>


            <div
                id="dashboard-greenhouses"
                class="greenhouses-dashboard-grid"
            ></div>

        </div>

    </section>


    {{-- =========================================================
         ALERTAS RECIENTES
    ========================================================== --}}

    <section
        id="recent-alerts-section"
        class="panel-card"
        style="margin-top:24px; display:none;"
    >

        <div class="panel-card-header">

            <div>

                <h2>
                    Alertas recientes
                </h2>

                <p>
                    Últimas incidencias detectadas por los sensores.
                </p>

            </div>


            <a
                href="{{ url('/alerts') }}"
                class="btn-secondary dashboard-link"
            >
                Ver todas
            </a>

        </div>


        <div class="panel-card-body">

            <div
                id="recent-alerts-empty"
                class="dashboard-state"
                style="display:none;"
            >

                <div class="empty-icon">
                    ✅
                </div>

                <strong>
                    Sin alertas registradas
                </strong>

                <span>
                    Cuando los sensores detecten incidencias,
                    aparecerán aquí.
                </span>

            </div>


            <div
                id="recent-alerts-list"
                class="recent-alerts-list"
            ></div>

        </div>

    </section>

</div>

@endsection


@push('styles')

<style>

    .dashboard-page {
        width: 100%;
    }


    /* =========================================================
       ENCABEZADO
    ========================================================== */

    .dashboard-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .dashboard-heading h2 {
        margin: 0;
        color: #173d27;
        font-size: 20px;
    }

    .dashboard-heading p {
        margin: 5px 0 0;
        color: #76827a;
        font-size: 11px;
    }


    /* =========================================================
       RESUMEN
    ========================================================== */

    .summary-grid {
        display: grid;
        grid-template-columns:
            repeat(5, minmax(0, 1fr));
        gap: 14px;
        margin-top: 20px;
    }

    .summary-card {
        min-height: 100px;
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

    .summary-card span {
        display: block;
        color: #748078;
        font-size: 10px;
        line-height: 1.3;
    }

    .summary-card strong {
        display: block;
        margin-top: 4px;
        color: #173d27;
        font-size: 25px;
    }

    .summary-alert {
        border-left: 4px solid #b54141;
    }

    .summary-alert .summary-icon {
        background: #fff0f0;
    }


    /* =========================================================
       ESTADOS
    ========================================================== */

    .dashboard-state {
        text-align: center;
        padding: 42px 20px;
        color: #748078;
    }

    .dashboard-state strong {
        display: block;
        margin-top: 8px;
        color: #415147;
        font-size: 14px;
    }

    .dashboard-state span {
        display: block;
        margin-top: 5px;
        font-size: 11px;
    }

    .empty-icon {
        font-size: 30px;
    }


    /* =========================================================
       INVERNADEROS
    ========================================================== */

    .greenhouses-dashboard-grid {
        display: grid;
        gap: 18px;
    }

    .dashboard-greenhouse {
        border: 1px solid #e0e8e2;
        border-radius: 14px;
        background: #ffffff;
        overflow: hidden;
    }

    .greenhouse-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
        padding: 17px 18px;
        border-bottom: 1px solid #edf1ee;
    }

    .greenhouse-title {
        margin: 0;
        color: #173d27;
        font-size: 16px;
    }

    .greenhouse-subtitle {
        margin: 5px 0 0;
        color: #7b867f;
        font-size: 10px;
    }

    .greenhouse-alert-badge {
        padding: 6px 10px;
        border-radius: 20px;
        background: #eaf7ee;
        color: #176136;
        font-size: 10px;
        font-weight: 700;
    }

    .greenhouse-alert-badge.has-alerts {
        background: #fff0f0;
        color: #a42e2e;
    }


    /* =========================================================
       MÉTRICAS
    ========================================================== */

    .metrics-grid {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: 14px;
        padding: 18px;
    }

    .metric-card {
        padding: 15px;
        background: #f7faf8;
        border: 1px solid #e3eae5;
        border-radius: 11px;
    }

    .metric-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .metric-name {
        color: #55645b;
        font-size: 11px;
        font-weight: 700;
    }

    .connection-badge {
        padding: 4px 7px;
        border-radius: 20px;
        font-size: 8px;
        font-weight: 700;
    }

    .connection-connected {
        background: #e7f5eb;
        color: #176136;
    }

    .connection-disconnected {
        background: #fff0f0;
        color: #a22e2e;
    }

    .connection-no-data {
        background: #ecefed;
        color: #6e766f;
    }

    .metric-value {
        margin-top: 11px;
        color: #173d27;
        font-size: 29px;
        font-weight: 700;
    }

    .metric-status {
        display: inline-flex;
        margin-top: 9px;
        padding: 5px 8px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 700;
    }

    .metric-status-normal,
    .metric-status-medium {
        background: #e9f6ed;
        color: #176136;
    }

    .metric-status-low,
    .metric-status-high {
        background: #fff0f0;
        color: #a32f2f;
    }

    .metric-status-no_data {
        background: #ecefed;
        color: #6e766f;
    }

    .metric-sensor {
        margin-top: 8px;
        color: #7d8780;
        font-size: 9px;
    }


    /* =========================================================
       INFORMACIÓN DEL INVERNADERO
    ========================================================== */

    .greenhouse-info-grid {
        display: grid;
        grid-template-columns:
            repeat(5, minmax(0, 1fr));
        gap: 10px;
        padding: 0 18px 18px;
    }

    .greenhouse-info-item {
        padding: 10px;
        background: #f8faf9;
        border-radius: 8px;
    }

    .greenhouse-info-item span {
        display: block;
        color: #7c8780;
        font-size: 9px;
    }

    .greenhouse-info-item strong {
        display: block;
        margin-top: 4px;
        color: #384a3e;
        font-size: 11px;
    }


    /* =========================================================
       ALERTAS RECIENTES
    ========================================================== */

    .dashboard-link {
        text-decoration: none;
    }

    .recent-alerts-list {
        display: grid;
        gap: 12px;
    }

    .recent-alert-item {
        display: grid;
        grid-template-columns:
            minmax(180px, 1fr)
            minmax(140px, .8fr)
            minmax(100px, .5fr);
        gap: 15px;
        align-items: center;
        padding: 13px 15px;
        border: 1px solid #e3e9e5;
        border-radius: 10px;
        background: #ffffff;
    }

    .recent-alert-title {
        margin: 0;
        color: #35463b;
        font-size: 11px;
    }

    .recent-alert-message {
        margin-top: 4px;
        color: #7a857d;
        font-size: 9px;
        line-height: 1.4;
    }

    .recent-alert-location {
        color: #5f6e65;
        font-size: 10px;
    }

    .recent-alert-reading {
        text-align: right;
    }

    .recent-alert-reading strong {
        display: block;
        color: #173d27;
        font-size: 16px;
    }

    .recent-alert-reading span {
        color: #7b867f;
        font-size: 8px;
    }

    .recent-alert-status {
        display: inline-flex;
        margin-top: 5px;
        padding: 4px 7px;
        border-radius: 20px;
        font-size: 8px;
        font-weight: 700;
    }

    .alert-active {
        background: #fff0f0;
        color: #a42e2e;
    }

    .alert-acknowledged {
        background: #fff7e9;
        color: #9a681f;
    }

    .alert-resolved {
        background: #eaf7ee;
        color: #176136;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 1150px) {

        .summary-grid {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

        .greenhouse-info-grid {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

    }


    @media (max-width: 850px) {

        .summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .metrics-grid {
            grid-template-columns: 1fr;
        }

        .greenhouse-info-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .recent-alert-item {
            grid-template-columns: 1fr;
        }

        .recent-alert-reading {
            text-align: left;
        }

    }


    @media (max-width: 550px) {

        .summary-grid,
        .greenhouse-info-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-heading {
            align-items: flex-start;
            flex-direction: column;
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


    const summaryGreenhouses =
        document.getElementById(
            'summary-greenhouses'
        );

    const summaryZones =
        document.getElementById(
            'summary-zones'
        );

    const summaryDevices =
        document.getElementById(
            'summary-devices'
        );

    const summarySensors =
        document.getElementById(
            'summary-sensors'
        );

    const summaryAlerts =
        document.getElementById(
            'summary-alerts'
        );


    const dashboardLoading =
        document.getElementById(
            'dashboard-loading'
        );

    const dashboardMessage =
        document.getElementById(
            'dashboard-message'
        );

    const greenhousesSection =
        document.getElementById(
            'greenhouses-section'
        );

    const greenhouseList =
        document.getElementById(
            'dashboard-greenhouses'
        );

    const greenhouseEmpty =
        document.getElementById(
            'dashboard-greenhouses-empty'
        );

    const greenhouseCount =
        document.getElementById(
            'dashboard-greenhouses-count'
        );


    const recentAlertsSection =
        document.getElementById(
            'recent-alerts-section'
        );

    const recentAlertsList =
        document.getElementById(
            'recent-alerts-list'
        );

    const recentAlertsEmpty =
        document.getElementById(
            'recent-alerts-empty'
        );


    const refreshDashboardButton =
        document.getElementById(
            'refresh-dashboard'
        );


    /*
    |--------------------------------------------------------------------------
    | UTILIDADES
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const div =
            document.createElement(
                'div'
            );

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


    function showDashboardMessage(
        text,
        type = 'error'
    ) {

        dashboardMessage.className =
            'panel-message ' +
            (
                type === 'success'
                    ? 'panel-message-success'
                    : 'panel-message-error'
            );

        dashboardMessage.textContent =
            text;

        dashboardMessage.style.display =
            'block';
    }


    function hideDashboardMessage() {

        dashboardMessage.style.display =
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
                dateStyle: 'short',
                timeStyle: 'short'
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    async function loadDashboard() {

        hideDashboardMessage();


        dashboardLoading.style.display =
            'block';


        refreshDashboardButton.disabled =
            true;


        refreshDashboardButton.textContent =
            'Actualizando...';


        try {

            const response =
                await fetch(
                    '/api/dashboard',
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

                showDashboardMessage(
                    result.message
                    ?? 'No fue posible cargar el dashboard.'
                );

                return;
            }


            const data =
                result.data ?? {};


            renderSummary(
                data.summary ?? {}
            );


            renderGreenhouses(
                data.greenhouses ?? []
            );


            renderRecentAlerts(
                data.recent_alerts ?? []
            );


            dashboardLoading.style.display =
                'none';


            greenhousesSection.style.display =
                'block';


            recentAlertsSection.style.display =
                'block';


        } catch (error) {

            dashboardLoading.style.display =
                'none';


            showDashboardMessage(
                'No fue posible conectar con el servidor.'
            );


        } finally {

            refreshDashboardButton.disabled =
                false;


            refreshDashboardButton.textContent =
                'Actualizar datos';
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

        summaryGreenhouses.textContent =
            summary.greenhouses ?? 0;


        summaryZones.textContent =
            summary.zones ?? 0;


        summaryDevices.textContent =
            summary.devices ?? 0;


        summarySensors.textContent =
            summary.sensors ?? 0;


        summaryAlerts.textContent =
            summary.active_alerts ?? 0;
    }


    /*
    |--------------------------------------------------------------------------
    | INVERNADEROS
    |--------------------------------------------------------------------------
    */

    function renderGreenhouses(
        greenhouses
    ) {

        greenhouseList.innerHTML =
            '';


        greenhouseCount.textContent =
            `${greenhouses.length} invernadero${greenhouses.length === 1 ? '' : 's'}`;


        if (!greenhouses.length) {

            greenhouseEmpty.style.display =
                'block';

            return;
        }


        greenhouseEmpty.style.display =
            'none';


        greenhouses.forEach(
            greenhouse => {

                const card =
                    document.createElement(
                        'article'
                    );


                card.className =
                    'dashboard-greenhouse';


                const alerts =
                    Number(
                        greenhouse.active_alerts
                        ?? 0
                    );


                card.innerHTML = `

                    <div class="greenhouse-header">

                        <div>

                            <h3 class="greenhouse-title">
                                ${escapeHtml(
                                    greenhouse.name
                                )}
                            </h3>

                            <p class="greenhouse-subtitle">

                                ${escapeHtml(
                                    greenhouse.crop_type
                                    ?? 'Cultivo sin definir'
                                )}

                                ·

                                ${escapeHtml(
                                    greenhouse.location
                                    ?? 'Ubicación sin definir'
                                )}

                            </p>

                        </div>


                        <span
                            class="
                                greenhouse-alert-badge
                                ${alerts > 0
                                    ? 'has-alerts'
                                    : ''}
                            "
                        >

                            ${
                                alerts > 0
                                    ? `🚨 ${alerts} alerta${alerts === 1 ? '' : 's'}`
                                    : '✓ Sin alertas'
                            }

                        </span>

                    </div>


                    <div class="metrics-grid">

                        ${metricHtml(
                            '🌡️',
                            'Temperatura',
                            greenhouse.metrics?.temperature
                        )}

                        ${metricHtml(
                            '💧',
                            'Humedad del suelo',
                            greenhouse.metrics?.soil_humidity
                        )}

                        ${metricHtml(
                            '🌫️',
                            'Humedad ambiental',
                            greenhouse.metrics?.ambient_humidity
                        )}

                    </div>


                    <div class="greenhouse-info-grid">

                        <div class="greenhouse-info-item">

                            <span>
                                Zonas
                            </span>

                            <strong>
                                ${greenhouse.zones_count ?? 0}
                            </strong>

                        </div>


                        <div class="greenhouse-info-item">

                            <span>
                                Dispositivos
                            </span>

                            <strong>
                                ${greenhouse.devices_count ?? 0}
                            </strong>

                        </div>


                        <div class="greenhouse-info-item">

                            <span>
                                Sensores
                            </span>

                            <strong>
                                ${greenhouse.sensors_count ?? 0}
                            </strong>

                        </div>


                        <div class="greenhouse-info-item">

                            <span>
                                Cultivo
                            </span>

                            <strong>
                                ${escapeHtml(
                                    greenhouse.crop_type
                                    ?? 'Sin definir'
                                )}
                            </strong>

                        </div>


                        <div class="greenhouse-info-item">

                            <span>
                                Ubicación
                            </span>

                            <strong>
                                ${escapeHtml(
                                    greenhouse.location
                                    ?? 'Sin definir'
                                )}
                            </strong>

                        </div>

                    </div>
                `;


                greenhouseList.appendChild(
                    card
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MÉTRICA
    |--------------------------------------------------------------------------
    */

    function metricHtml(
        icon,
        name,
        metric
    ) {

        metric =
            metric ?? {};


        const value =
            metric.value !== null
            && metric.value !== undefined
                ? `${Number(
                    metric.value
                ).toFixed(1)} ${escapeHtml(
                    metric.unit ?? ''
                )}`
                : '--';


        const connectionStatus =
            metric.connection_status
            ?? 'no_data';


        const status =
            metric.status
            ?? 'no_data';


        return `

            <div class="metric-card">

                <div class="metric-header">

                    <span class="metric-name">
                        ${icon} ${escapeHtml(name)}
                    </span>


                    <span
                        class="
                            connection-badge
                            connection-${escapeHtml(
                                connectionStatus
                            )}
                        "
                    >
                        ${escapeHtml(
                            metric.connection_label
                            ?? 'Sin datos'
                        )}
                    </span>

                </div>


                <div class="metric-value">
                    ${value}
                </div>


                <span
                    class="
                        metric-status
                        metric-status-${escapeHtml(
                            status
                        )}
                    "
                >
                    ${escapeHtml(
                        metric.status_label
                        ?? 'Sin datos'
                    )}
                </span>


                <div class="metric-sensor">

                    ${
                        metric.sensor_name
                            ? `Sensor: ${escapeHtml(
                                metric.sensor_name
                            )}`
                            : 'Sin sensor con lecturas'
                    }

                </div>

            </div>
        `;
    }


    /*
    |--------------------------------------------------------------------------
    | ALERTAS RECIENTES
    |--------------------------------------------------------------------------
    */

    function renderRecentAlerts(
        alerts
    ) {

        recentAlertsList.innerHTML =
            '';


        if (!alerts.length) {

            recentAlertsEmpty.style.display =
                'block';

            return;
        }


        recentAlertsEmpty.style.display =
            'none';


        alerts.forEach(
            alert => {

                const item =
                    document.createElement(
                        'article'
                    );


                item.className =
                    'recent-alert-item';


                const reading =
                    alert.reading;


                item.innerHTML = `

                    <div>

                        <h3 class="recent-alert-title">
                            ${escapeHtml(
                                alert.message
                            )}
                        </h3>

                        <div class="recent-alert-message">

                            ${escapeHtml(
                                alert.sensor?.name
                                ?? 'Sensor'
                            )}

                            ·

                            ${escapeHtml(
                                formatDate(
                                    alert.created_at
                                )
                            )}

                        </div>

                    </div>


                    <div class="recent-alert-location">

                        <strong>
                            ${escapeHtml(
                                alert.greenhouse?.name
                                ?? 'Sin invernadero'
                            )}
                        </strong>

                        <br>

                        ${escapeHtml(
                            alert.zone?.name
                            ?? 'Sin zona'
                        )}

                    </div>


                    <div class="recent-alert-reading">

                        ${
                            reading
                                ? `
                                    <strong>
                                        ${Number(
                                            reading.value
                                        ).toFixed(1)}
                                        ${escapeHtml(
                                            alert.sensor?.unit
                                            ?? ''
                                        )}
                                    </strong>
                                `
                                : `
                                    <strong>
                                        --
                                    </strong>
                                `
                        }


                        <span
                            class="
                                recent-alert-status
                                alert-${escapeHtml(
                                    alert.status
                                )}
                            "
                        >

                            ${
                                alert.status === 'active'
                                    ? 'Activa'
                                    : alert.status === 'acknowledged'
                                        ? 'Atendida'
                                        : 'Resuelta'
                            }

                        </span>

                    </div>
                `;


                recentAlertsList.appendChild(
                    item
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR
    |--------------------------------------------------------------------------
    */

    refreshDashboardButton.addEventListener(
        'click',
        loadDashboard
    );


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    loadDashboard();


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZACIÓN AUTOMÁTICA CADA 5 MINUTOS
    |--------------------------------------------------------------------------
    */

    setInterval(
        loadDashboard,
        5 * 60 * 1000
    );

</script>

@endpush