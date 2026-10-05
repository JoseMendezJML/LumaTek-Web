@extends('layouts.app')

@section('title', 'Reportes | LumaTek')

@section('page-title', 'Reportes')

@section(
    'page-subtitle',
    'Genera un resumen de monitoreo por invernadero y periodo'
)

@section('content')

<div class="reports-page">

    {{-- =========================================================
         FILTROS
    ========================================================== --}}

    <section class="report-card">

        <div class="report-section-header">

            <div>

                <h2>
                    Generar reporte
                </h2>

                <p>
                    Selecciona un invernadero y el periodo
                    que deseas consultar.
                </p>

            </div>

        </div>


        <form id="report-filter-form">

            <div class="report-filters-grid">

                <div class="report-form-group">

                    <label for="report-greenhouse">
                        Invernadero
                    </label>

                    <select
                        id="report-greenhouse"
                        required
                    >

                        <option value="">
                            Selecciona un invernadero
                        </option>

                    </select>

                </div>


                <div class="report-form-group">

                    <label for="report-start-date">
                        Fecha inicial
                    </label>

                    <input
                        type="date"
                        id="report-start-date"
                        required
                    >

                </div>


                <div class="report-form-group">

                    <label for="report-end-date">
                        Fecha final
                    </label>

                    <input
                        type="date"
                        id="report-end-date"
                        required
                    >

                </div>


                <div class="report-form-group report-filter-action">

                    <button
                        type="submit"
                        id="report-generate-button"
                        class="report-primary-button"
                    >
                        Generar reporte
                    </button>

                </div>

            </div>

        </form>

    </section>


    {{-- =========================================================
         MENSAJES
    ========================================================== --}}

    <div
        id="report-message"
        class="report-message"
        style="display:none;"
    ></div>


    {{-- =========================================================
         ESTADO INICIAL
    ========================================================== --}}

    <section
        id="report-empty-state"
        class="report-empty-state"
    >

        <strong>
            Selecciona un periodo para generar el reporte
        </strong>

        <span>
            Aquí aparecerá el resumen de monitoreo,
            alertas y riegos del invernadero.
        </span>

    </section>


    {{-- =========================================================
         REPORTE
    ========================================================== --}}

    <div
        id="report-content"
        style="display:none;"
    >

        {{-- INFORMACIÓN GENERAL --}}

        <section class="report-card">

            <div class="report-section-header">

    <div>

        <h2>
            Información del reporte
        </h2>

        <p>
            Datos generales del invernadero
            y del periodo consultado.
        </p>

    </div>


    <button
        type="button"
        id="report-pdf-button"
        class="report-pdf-button"
    >
        Descargar PDF
    </button>

</div>


            <div class="report-info-grid">

                <div class="report-info-item">

                    <span>
                        Empresa
                    </span>

                    <strong id="report-company-name">
                        —
                    </strong>

                </div>


                <div class="report-info-item">

                    <span>
                        Invernadero
                    </span>

                    <strong id="report-greenhouse-name">
                        —
                    </strong>

                </div>


                <div class="report-info-item">

                    <span>
                        Cultivo
                    </span>

                    <strong id="report-crop-type">
                        —
                    </strong>

                </div>


                <div class="report-info-item">

                    <span>
                        Ubicación
                    </span>

                    <strong id="report-location">
                        —
                    </strong>

                </div>


                <div class="report-info-item">

                    <span>
                        Periodo
                    </span>

                    <strong id="report-period">
                        —
                    </strong>

                </div>


                <div class="report-info-item">

                    <span>
                        Generado por
                    </span>

                    <strong id="report-generated-by">
                        —
                    </strong>

                </div>

            </div>

        </section>


        {{-- =====================================================
             VARIABLES AMBIENTALES
        ====================================================== --}}

        <section class="report-block">

            <div class="report-block-title">

                <h2>
                    Resumen de monitoreo
                </h2>

                <p>
                    Valores registrados durante el periodo seleccionado.
                </p>

            </div>


            <div class="metrics-grid">

                {{-- TEMPERATURA --}}

                <article class="metric-report-card">

                    <div class="metric-card-header">

                        <div class="metric-code">
                            TMP
                        </div>

                        <div>
                            <h3>
                                Temperatura
                            </h3>

                            <span>
                                °C
                            </span>
                        </div>

                    </div>


                    <div class="metric-average">

                        <span>
                            Promedio
                        </span>

                        <strong id="temperature-average">
                            —
                        </strong>

                    </div>


                    <div class="metric-details">

                        <div>

                            <span>
                                Mínimo
                            </span>

                            <strong id="temperature-minimum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Máximo
                            </span>

                            <strong id="temperature-maximum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Lecturas
                            </span>

                            <strong id="temperature-total">
                                0
                            </strong>

                        </div>

                    </div>

                </article>


                {{-- HUMEDAD DEL SUELO --}}

                <article class="metric-report-card">

                    <div class="metric-card-header">

                        <div class="metric-code">
                            HSU
                        </div>

                        <div>

                            <h3>
                                Humedad del suelo
                            </h3>

                            <span>
                                %
                            </span>

                        </div>

                    </div>


                    <div class="metric-average">

                        <span>
                            Promedio
                        </span>

                        <strong id="soil-average">
                            —
                        </strong>

                    </div>


                    <div class="metric-details">

                        <div>

                            <span>
                                Mínimo
                            </span>

                            <strong id="soil-minimum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Máximo
                            </span>

                            <strong id="soil-maximum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Lecturas
                            </span>

                            <strong id="soil-total">
                                0
                            </strong>

                        </div>

                    </div>

                </article>


                {{-- HUMEDAD AMBIENTAL --}}

                <article class="metric-report-card">

                    <div class="metric-card-header">

                        <div class="metric-code">
                            HAM
                        </div>

                        <div>

                            <h3>
                                Humedad ambiental
                            </h3>

                            <span>
                                %
                            </span>

                        </div>

                    </div>


                    <div class="metric-average">

                        <span>
                            Promedio
                        </span>

                        <strong id="ambient-average">
                            —
                        </strong>

                    </div>


                    <div class="metric-details">

                        <div>

                            <span>
                                Mínimo
                            </span>

                            <strong id="ambient-minimum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Máximo
                            </span>

                            <strong id="ambient-maximum">
                                —
                            </strong>

                        </div>


                        <div>

                            <span>
                                Lecturas
                            </span>

                            <strong id="ambient-total">
                                0
                            </strong>

                        </div>

                    </div>

                </article>

            </div>

        </section>


        {{-- =====================================================
             ALERTAS Y RIEGOS
        ====================================================== --}}

        <section class="report-summary-grid">

            {{-- ALERTAS --}}

            <article class="report-card">

                <div class="report-section-header">

                    <div>

                        <h2>
                            Alertas
                        </h2>

                        <p>
                            Resumen de incidencias del periodo.
                        </p>

                    </div>

                </div>


                <div class="report-stat-main">

                    <span>
                        Total
                    </span>

                    <strong id="alerts-total">
                        0
                    </strong>

                </div>


                <div class="report-stat-grid">

                    <div>

                        <span>
                            Activas
                        </span>

                        <strong id="alerts-active">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Atendidas
                        </span>

                        <strong id="alerts-acknowledged">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Resueltas
                        </span>

                        <strong id="alerts-resolved">
                            0
                        </strong>

                    </div>

                </div>


                <div class="report-severity-grid">

                    <div>

                        <span>
                            Informativas
                        </span>

                        <strong id="alerts-info">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Advertencias
                        </span>

                        <strong id="alerts-warning">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Críticas
                        </span>

                        <strong id="alerts-critical">
                            0
                        </strong>

                    </div>

                </div>

            </article>


            {{-- RIEGOS --}}

            <article class="report-card">

                <div class="report-section-header">

                    <div>

                        <h2>
                            Riegos
                        </h2>

                        <p>
                            Actividad de riego registrada.
                        </p>

                    </div>

                </div>


                <div class="report-stat-main">

                    <span>
                        Total
                    </span>

                    <strong id="irrigation-total">
                        0
                    </strong>

                </div>


                <div class="report-stat-grid">

                    <div>

                        <span>
                            Manuales
                        </span>

                        <strong id="irrigation-manual">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Automáticos
                        </span>

                        <strong id="irrigation-automatic">
                            0
                        </strong>

                    </div>


                    <div>

                        <span>
                            Completados
                        </span>

                        <strong id="irrigation-completed">
                            0
                        </strong>

                    </div>

                </div>


                <div class="report-irrigation-extra">

                    <div>

                        <span>
                            Duración acumulada
                        </span>

                        <strong id="irrigation-duration">
                            0 min
                        </strong>

                    </div>


                    <div>

                        <span>
                            Agua registrada
                        </span>

                        <strong id="irrigation-water">
                            0 L
                        </strong>

                    </div>

                </div>

            </article>

        </section>


        {{-- =====================================================
             ALERTAS RECIENTES
        ====================================================== --}}

        <section class="report-card">

            <div class="report-section-header">

                <div>

                    <h2>
                        Alertas del periodo
                    </h2>

                    <p>
                        Últimas alertas encontradas en el reporte.
                    </p>

                </div>

            </div>


            <div
                id="recent-alerts-empty"
                class="report-table-empty"
                style="display:none;"
            >
                No se registraron alertas durante este periodo.
            </div>


            <div
                id="recent-alerts-wrapper"
                class="report-table-wrapper"
            >

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Zona
                            </th>

                            <th>
                                Severidad
                            </th>

                            <th>
                                Estado
                            </th>

                        </tr>

                    </thead>


                    <tbody id="recent-alerts-body"></tbody>

                </table>

            </div>

        </section>


        {{-- =====================================================
             RIEGOS RECIENTES
        ====================================================== --}}

        <section class="report-card">

            <div class="report-section-header">

                <div>

                    <h2>
                        Riegos del periodo
                    </h2>

                    <p>
                        Últimos eventos de riego encontrados.
                    </p>

                </div>

            </div>


            <div
                id="recent-irrigations-empty"
                class="report-table-empty"
                style="display:none;"
            >
                No se registraron riegos durante este periodo.
            </div>


            <div
                id="recent-irrigations-wrapper"
                class="report-table-wrapper"
            >

                <table class="report-table">

                    <thead>

                        <tr>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Zona
                            </th>

                            <th>
                                Modalidad
                            </th>

                            <th>
                                Duración
                            </th>

                            <th>
                                Agua
                            </th>

                            <th>
                                Estado
                            </th>

                        </tr>

                    </thead>


                    <tbody id="recent-irrigations-body"></tbody>

                </table>

            </div>

        </section>

    </div>

</div>

@endsection


{{-- =============================================================
     ESTILOS
============================================================= --}}

@push('styles')

<style>

    .report-pdf-button {
    min-height: 38px;

    padding: 8px 14px;

    border: 1px solid #176136;
    border-radius: 8px;

    background: #ffffff;

    color: #176136;

    cursor: pointer;

    font-size: 10px;
    font-weight: 700;
}

.report-pdf-button:hover {
    background: #edf6ef;
}

.report-pdf-button:disabled {
    opacity: .55;
    cursor: not-allowed;
}

    .reports-page {
        width: 100%;
    }


    .report-card {
        margin-bottom: 20px;

        padding: 20px;

        background: #ffffff;

        border: 1px solid #e0e8e2;
        border-radius: 14px;
    }


    .report-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 15px;

        margin-bottom: 18px;
    }


    .report-section-header h2,
    .report-block-title h2 {
        margin: 0;

        color: #173d27;

        font-size: 17px;
    }


    .report-section-header p,
    .report-block-title p {
        margin: 5px 0 0;

        color: #78837c;

        font-size: 10px;
    }


    /* =========================================================
       FILTROS
    ========================================================== */

    .report-filters-grid {
        display: grid;

        grid-template-columns:
            1.4fr
            1fr
            1fr
            auto;

        gap: 14px;

        align-items: end;
    }


    .report-form-group {
        display: flex;
        flex-direction: column;

        gap: 6px;
    }


    .report-form-group label {
        color: #55645b;

        font-size: 10px;
        font-weight: 700;
    }


    .report-form-group input,
    .report-form-group select {
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


    .report-form-group input:focus,
    .report-form-group select:focus {
        border-color: #4c8b62;

        box-shadow:
            0 0 0 2px
            rgba(76, 139, 98, .10);
    }


    .report-primary-button {
        min-height: 40px;

        padding: 9px 17px;

        border: 0;
        border-radius: 8px;

        background: #176136;

        color: #ffffff;

        cursor: pointer;

        font-size: 10px;
        font-weight: 700;
    }


    .report-primary-button:hover {
        background: #104c2a;
    }


    .report-primary-button:disabled {
        opacity: .55;

        cursor: not-allowed;
    }


    /* =========================================================
       MENSAJES
    ========================================================== */

    .report-message {
        margin-bottom: 20px;

        padding: 12px 14px;

        border-radius: 9px;

        font-size: 11px;
    }


    .report-message-success {
        border: 1px solid #bde0c6;

        background: #edf8f0;

        color: #176136;
    }


    .report-message-error {
        border: 1px solid #efc2c2;

        background: #fff2f2;

        color: #9a2929;
    }


    /* =========================================================
       ESTADO INICIAL
    ========================================================== */

    .report-empty-state {
        min-height: 220px;

        display: flex;
        flex-direction: column;

        align-items: center;
        justify-content: center;

        gap: 7px;

        padding: 25px;

        border: 1px dashed #cfdad2;
        border-radius: 14px;

        background: #f8faf8;

        text-align: center;
    }


    .report-empty-state strong {
        color: #314239;

        font-size: 14px;
    }


    .report-empty-state span {
        color: #849088;

        font-size: 10px;
    }


    /* =========================================================
       INFORMACIÓN DEL REPORTE
    ========================================================== */

    .report-info-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 12px;
    }


    .report-info-item {
        padding: 14px;

        border: 1px solid #e5ebe7;
        border-radius: 10px;

        background: #f8faf8;
    }


    .report-info-item span {
        display: block;

        color: #7b867f;

        font-size: 9px;
    }


    .report-info-item strong {
        display: block;

        margin-top: 6px;

        color: #24392d;

        font-size: 12px;

        overflow-wrap: anywhere;
    }


    /* =========================================================
       MÉTRICAS
    ========================================================== */

    .report-block {
        margin-bottom: 20px;
    }


    .report-block-title {
        margin-bottom: 15px;
    }


    .metrics-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 14px;
    }


    .metric-report-card {
        padding: 18px;

        border: 1px solid #e0e8e2;
        border-radius: 13px;

        background: #ffffff;
    }


    .metric-card-header {
        display: flex;
        align-items: center;

        gap: 11px;
    }


    .metric-code {
        width: 42px;
        height: 42px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        border-radius: 10px;

        background: #edf6ef;

        color: #176136;

        font-size: 10px;
        font-weight: 800;
    }


    .metric-card-header h3 {
        margin: 0;

        color: #263b2f;

        font-size: 13px;
    }


    .metric-card-header span {
        display: block;

        margin-top: 3px;

        color: #849087;

        font-size: 9px;
    }


    .metric-average {
        margin-top: 20px;
    }


    .metric-average span {
        display: block;

        color: #7d8981;

        font-size: 9px;
    }


    .metric-average strong {
        display: block;

        margin-top: 5px;

        color: #173d27;

        font-size: 28px;
    }


    .metric-details {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 8px;

        margin-top: 18px;
        padding-top: 14px;

        border-top: 1px solid #edf1ee;
    }


    .metric-details span {
        display: block;

        color: #849087;

        font-size: 8px;
    }


    .metric-details strong {
        display: block;

        margin-top: 4px;

        color: #405248;

        font-size: 11px;
    }


    /* =========================================================
       ALERTAS Y RIEGOS
    ========================================================== */

    .report-summary-grid {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 15px;
    }


    .report-stat-main {
        padding-bottom: 14px;

        border-bottom: 1px solid #edf1ee;
    }


    .report-stat-main span {
        color: #7d8981;

        font-size: 9px;
    }


    .report-stat-main strong {
        display: block;

        margin-top: 5px;

        color: #173d27;

        font-size: 27px;
    }


    .report-stat-grid,
    .report-severity-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 9px;

        margin-top: 14px;
    }


    .report-stat-grid div,
    .report-severity-grid div,
    .report-irrigation-extra div {
        padding: 11px;

        border-radius: 9px;

        background: #f7f9f7;
    }


    .report-stat-grid span,
    .report-severity-grid span,
    .report-irrigation-extra span {
        display: block;

        color: #849087;

        font-size: 8px;
    }


    .report-stat-grid strong,
    .report-severity-grid strong,
    .report-irrigation-extra strong {
        display: block;

        margin-top: 5px;

        color: #34483c;

        font-size: 12px;
    }


    .report-irrigation-extra {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 9px;

        margin-top: 14px;
    }


    /* =========================================================
       TABLAS
    ========================================================== */

    .report-table-wrapper {
        width: 100%;

        overflow-x: auto;
    }


    .report-table {
        width: 100%;

        border-collapse: collapse;
    }


    .report-table th {
        padding: 11px 10px;

        border-bottom: 1px solid #dfe7e1;

        color: #637168;

        text-align: left;

        font-size: 9px;
        font-weight: 700;

        white-space: nowrap;
    }


    .report-table td {
        padding: 12px 10px;

        border-bottom: 1px solid #edf1ee;

        color: #425249;

        font-size: 10px;
    }


    .report-table tbody tr:last-child td {
        border-bottom: 0;
    }


    .report-table-empty {
        padding: 24px;

        border: 1px dashed #d8e0da;
        border-radius: 10px;

        background: #f9faf9;

        color: #87928a;

        text-align: center;

        font-size: 10px;
    }


    .report-status {
        display: inline-flex;

        padding: 4px 7px;

        border-radius: 20px;

        background: #edf3ef;

        color: #496155;

        font-size: 8px;
        font-weight: 700;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 1050px) {

        .report-filters-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .metrics-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .report-info-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }


    @media (max-width: 760px) {

        .report-filters-grid,
        .metrics-grid,
        .report-summary-grid,
        .report-info-grid {
            grid-template-columns: 1fr;
        }


        .report-primary-button {
            width: 100%;
        }

    }


    @media (max-width: 480px) {

        .metric-details,
        .report-stat-grid,
        .report-severity-grid,
        .report-irrigation-extra {
            grid-template-columns: 1fr;
        }

    }

</style>

@endpush


{{-- =============================================================
     JAVASCRIPT
============================================================= --}}

@push('scripts')

<script>

    const reportPdfButton =
    document.getElementById(
        'report-pdf-button'
    );

    const reportFilterForm =
        document.getElementById(
            'report-filter-form'
        );


    const reportGreenhouse =
        document.getElementById(
            'report-greenhouse'
        );


    const reportStartDate =
        document.getElementById(
            'report-start-date'
        );


    const reportEndDate =
        document.getElementById(
            'report-end-date'
        );


    const reportGenerateButton =
        document.getElementById(
            'report-generate-button'
        );


    const reportMessage =
        document.getElementById(
            'report-message'
        );


    const reportEmptyState =
        document.getElementById(
            'report-empty-state'
        );


    const reportContent =
        document.getElementById(
            'report-content'
        );


    /*
    |--------------------------------------------------------------------------
    | TOKEN
    |--------------------------------------------------------------------------
    */

    function getReportToken() {

        return sessionStorage.getItem(
            'lumatek_access_token'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FECHAS INICIALES
    |--------------------------------------------------------------------------
    */

    function setDefaultDates() {

        const end =
            new Date();

        const start =
            new Date();

        start.setDate(
            start.getDate() - 7
        );


        reportStartDate.value =
            formatInputDate(
                start
            );


        reportEndDate.value =
            formatInputDate(
                end
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
    | INVERNADEROS
    |--------------------------------------------------------------------------
    */

    async function loadReportGreenhouses() {

        const token =
            getReportToken();


        if (!token) {

            window.location.href =
                '/login';

            return;
        }


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

                sessionStorage.removeItem(
                    'lumatek_access_token'
                );

                sessionStorage.removeItem(
                    'lumatek_user'
                );

                window.location.href =
                    '/login';

                return;
            }


            const result =
                await response.json();


            if (!response.ok) {

                throw new Error(
                    result.message
                    ?? 'No fue posible cargar los invernaderos.'
                );
            }


            const greenhouses =
                result.data ?? [];


            reportGreenhouse.innerHTML = `

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


                    reportGreenhouse.appendChild(
                        option
                    );
                }
            );


            if (
                greenhouses.length === 1
            ) {

                reportGreenhouse.value =
                    greenhouses[0].id;
            }


        } catch (error) {

            showReportMessage(
                error.message,
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GENERAR REPORTE
    |--------------------------------------------------------------------------
    */

    reportFilterForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            hideReportMessage();


            const greenhouseId =
                reportGreenhouse.value;


            const startDate =
                reportStartDate.value;


            const endDate =
                reportEndDate.value;


            if (!greenhouseId) {

                showReportMessage(
                    'Selecciona un invernadero.',
                    'error'
                );

                return;
            }


            if (
                !startDate
                || !endDate
            ) {

                showReportMessage(
                    'Selecciona el periodo del reporte.',
                    'error'
                );

                return;
            }


            if (
                startDate > endDate
            ) {

                showReportMessage(
                    'La fecha inicial no puede ser mayor que la fecha final.',
                    'error'
                );

                return;
            }


            await generateReport(
                greenhouseId,
                startDate,
                endDate
            );
        }
    );


    async function generateReport(
        greenhouseId,
        startDate,
        endDate
    ) {

        const token =
            getReportToken();


        if (!token) {

            window.location.href =
                '/login';

            return;
        }


        reportGenerateButton.disabled =
            true;


        reportGenerateButton.textContent =
            'Generando...';


        try {

            const params =
                new URLSearchParams({
                    greenhouse_id:
                        greenhouseId,

                    start_date:
                        startDate,

                    end_date:
                        endDate
                });


            const response =
                await fetch(
                    `/api/reports?${params.toString()}`,
                    {
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


            if (response.status === 401) {

                sessionStorage.removeItem(
                    'lumatek_access_token'
                );

                sessionStorage.removeItem(
                    'lumatek_user'
                );

                window.location.href =
                    '/login';

                return;
            }


            if (!response.ok) {

                throw new Error(
                    result.message
                    ?? 'No fue posible generar el reporte.'
                );
            }


            renderReport(
                result.report
            );


            showReportMessage(
                result.message
                ?? 'Reporte generado correctamente.',
                'success'
            );


        } catch (error) {

            reportContent.style.display =
                'none';


            reportEmptyState.style.display =
                'flex';


            showReportMessage(
                error.message,
                'error'
            );


        } finally {

            reportGenerateButton.disabled =
                false;


            reportGenerateButton.textContent =
                'Generar reporte';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR REPORTE
    |--------------------------------------------------------------------------
    */

    function renderReport(
        report
    ) {

        reportEmptyState.style.display =
            'none';


        reportContent.style.display =
            'block';


        document.getElementById(
            'report-company-name'
        ).textContent =
            report.company?.name
            ?? '—';


        document.getElementById(
            'report-greenhouse-name'
        ).textContent =
            report.greenhouse?.name
            ?? '—';


        document.getElementById(
            'report-crop-type'
        ).textContent =
            report.greenhouse?.crop_type
            ?? 'Sin especificar';


        document.getElementById(
            'report-location'
        ).textContent =
            report.greenhouse?.location
            ?? 'Sin especificar';


        document.getElementById(
            'report-period'
        ).textContent =
            `${formatDateOnly(
                report.period?.start_date
            )} - ${formatDateOnly(
                report.period?.end_date
            )}`;


        document.getElementById(
            'report-generated-by'
        ).textContent =
            report.generated_by?.name
            ?? '—';


        renderMetric(
            report.metrics?.temperature,
            {
                average:
                    'temperature-average',

                minimum:
                    'temperature-minimum',

                maximum:
                    'temperature-maximum',

                total:
                    'temperature-total'
            }
        );


        renderMetric(
            report.metrics?.soil_humidity,
            {
                average:
                    'soil-average',

                minimum:
                    'soil-minimum',

                maximum:
                    'soil-maximum',

                total:
                    'soil-total'
            }
        );


        renderMetric(
            report.metrics?.ambient_humidity,
            {
                average:
                    'ambient-average',

                minimum:
                    'ambient-minimum',

                maximum:
                    'ambient-maximum',

                total:
                    'ambient-total'
            }
        );


        renderAlerts(
            report.alerts
        );


        renderIrrigation(
            report.irrigation
        );


        renderRecentAlerts(
            report.alerts?.recent
            ?? []
        );


        renderRecentIrrigations(
            report.irrigation?.recent
            ?? []
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MÉTRICAS
    |--------------------------------------------------------------------------
    */

    function renderMetric(
        metric,
        ids
    ) {

        const unit =
            metric?.unit
            ?? '';


        document.getElementById(
            ids.average
        ).textContent =
            formatMetricValue(
                metric?.average,
                unit
            );


        document.getElementById(
            ids.minimum
        ).textContent =
            formatMetricValue(
                metric?.minimum,
                unit
            );


        document.getElementById(
            ids.maximum
        ).textContent =
            formatMetricValue(
                metric?.maximum,
                unit
            );


        document.getElementById(
            ids.total
        ).textContent =
            metric?.total_readings
            ?? 0;
    }


    /*
    |--------------------------------------------------------------------------
    | ALERTAS
    |--------------------------------------------------------------------------
    */

    function renderAlerts(
        alerts
    ) {

        const summary =
            alerts?.summary
            ?? {};


        setText(
            'alerts-total',
            summary.total ?? 0
        );


        setText(
            'alerts-active',
            summary.active ?? 0
        );


        setText(
            'alerts-acknowledged',
            summary.acknowledged ?? 0
        );


        setText(
            'alerts-resolved',
            summary.resolved ?? 0
        );


        setText(
            'alerts-info',
            summary.info ?? 0
        );


        setText(
            'alerts-warning',
            summary.warning ?? 0
        );


        setText(
            'alerts-critical',
            summary.critical ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RIEGOS
    |--------------------------------------------------------------------------
    */

    function renderIrrigation(
        irrigation
    ) {

        const summary =
            irrigation?.summary
            ?? {};


        setText(
            'irrigation-total',
            summary.total ?? 0
        );


        setText(
            'irrigation-manual',
            summary.manual ?? 0
        );


        setText(
            'irrigation-automatic',
            summary.automatic ?? 0
        );


        setText(
            'irrigation-completed',
            summary.completed ?? 0
        );


        setText(
            'irrigation-duration',
            `${summary.total_duration_minutes ?? 0} min`
        );


        setText(
            'irrigation-water',
            `${formatNumber(
                summary.total_water_liters
                ?? 0
            )} L`
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TABLA DE ALERTAS
    |--------------------------------------------------------------------------
    */

    function renderRecentAlerts(
        alerts
    ) {

        const body =
            document.getElementById(
                'recent-alerts-body'
            );


        const wrapper =
            document.getElementById(
                'recent-alerts-wrapper'
            );


        const empty =
            document.getElementById(
                'recent-alerts-empty'
            );


        body.innerHTML =
            '';


        if (!alerts.length) {

            wrapper.style.display =
                'none';


            empty.style.display =
                'block';


            return;
        }


        wrapper.style.display =
            'block';


        empty.style.display =
            'none';


        alerts.forEach(
            alert => {

                const row =
                    document.createElement(
                        'tr'
                    );


                row.innerHTML = `

                    <td>
                        ${escapeHtml(
                            formatDateTime(
                                alert.created_at
                            )
                        )}
                    </td>

                    <td>
                        ${escapeHtml(
                            alert.type_label
                            ?? 'Alerta'
                        )}
                    </td>

                    <td>
                        ${escapeHtml(
                            alert.zone?.name
                            ?? 'Sin zona'
                        )}
                    </td>

                    <td>
                        <span class="report-status">
                            ${escapeHtml(
                                alert.severity_label
                                ?? alert.severity
                                ?? '—'
                            )}
                        </span>
                    </td>

                    <td>
                        <span class="report-status">
                            ${escapeHtml(
                                alert.status_label
                                ?? alert.status
                                ?? '—'
                            )}
                        </span>
                    </td>

                `;


                body.appendChild(
                    row
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TABLA DE RIEGOS
    |--------------------------------------------------------------------------
    */

    function renderRecentIrrigations(
        irrigations
    ) {

        const body =
            document.getElementById(
                'recent-irrigations-body'
            );


        const wrapper =
            document.getElementById(
                'recent-irrigations-wrapper'
            );


        const empty =
            document.getElementById(
                'recent-irrigations-empty'
            );


        body.innerHTML =
            '';


        if (!irrigations.length) {

            wrapper.style.display =
                'none';


            empty.style.display =
                'block';


            return;
        }


        wrapper.style.display =
            'block';


        empty.style.display =
            'none';


        irrigations.forEach(
            irrigation => {

                const row =
                    document.createElement(
                        'tr'
                    );


                row.innerHTML = `

                    <td>
                        ${escapeHtml(
                            formatDateTime(
                                irrigation.started_at
                            )
                        )}
                    </td>

                    <td>
                        ${escapeHtml(
                            irrigation.zone?.name
                            ?? 'Sin zona'
                        )}
                    </td>

                    <td>
                        ${escapeHtml(
                            irrigation.mode_label
                            ?? '—'
                        )}
                    </td>

                    <td>
                        ${
                            irrigation.duration_minutes
                            !== null
                            && irrigation.duration_minutes
                            !== undefined
                                ? `${escapeHtml(
                                    irrigation.duration_minutes
                                )} min`
                                : '—'
                        }
                    </td>

                    <td>
                        ${
                            irrigation.water_liters
                            !== null
                            && irrigation.water_liters
                            !== undefined
                                ? `${escapeHtml(
                                    formatNumber(
                                        irrigation.water_liters
                                    )
                                )} L`
                                : '—'
                        }
                    </td>

                    <td>
                        <span class="report-status">
                            ${escapeHtml(
                                irrigation.status_label
                                ?? irrigation.status
                                ?? '—'
                            )}
                        </span>
                    </td>

                `;


                body.appendChild(
                    row
                );
            }
        );
    }

    /*
|--------------------------------------------------------------------------
| DESCARGAR PDF
|--------------------------------------------------------------------------
*/

reportPdfButton.addEventListener(
    'click',
    async function () {

        const greenhouseId =
            reportGreenhouse.value;

        const startDate =
            reportStartDate.value;

        const endDate =
            reportEndDate.value;

        const token =
            getReportToken();


        if (
            !greenhouseId
            || !startDate
            || !endDate
        ) {

            showReportMessage(
                'Genera primero un reporte válido.',
                'error'
            );

            return;
        }


        if (!token) {

            window.location.href =
                '/login';

            return;
        }


        reportPdfButton.disabled =
            true;

        reportPdfButton.textContent =
            'Generando PDF...';


        try {

            const params =
                new URLSearchParams({
                    greenhouse_id:
                        greenhouseId,

                    start_date:
                        startDate,

                    end_date:
                        endDate
                });


            const response =
                await fetch(
                    `/api/reports/pdf?${params.toString()}`,
                    {
                        headers: {
                            'Accept':
                                'application/pdf',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (response.status === 401) {

                sessionStorage.removeItem(
                    'lumatek_access_token'
                );

                sessionStorage.removeItem(
                    'lumatek_user'
                );

                window.location.href =
                    '/login';

                return;
            }


            if (!response.ok) {

                let message =
                    'No fue posible generar el PDF.';


                try {

                    const error =
                        await response.json();

                    message =
                        error.message
                        ?? message;

                } catch (error) {
                    // La respuesta no era JSON.
                }


                throw new Error(
                    message
                );
            }


            const blob =
                await response.blob();


            const url =
                window.URL.createObjectURL(
                    blob
                );


            const link =
                document.createElement(
                    'a'
                );


            link.href =
                url;


            link.download =
                `reporte-lumatek-${greenhouseId}-${startDate}-${endDate}.pdf`;


            document.body.appendChild(
                link
            );


            link.click();


            link.remove();


            window.URL.revokeObjectURL(
                url
            );


        } catch (error) {

            showReportMessage(
                error.message,
                'error'
            );


        } finally {

            reportPdfButton.disabled =
                false;

            reportPdfButton.textContent =
                'Descargar PDF';
        }
    }
);


    /*
    |--------------------------------------------------------------------------
    | UTILIDADES
    |--------------------------------------------------------------------------
    */

    function setText(
        id,
        value
    ) {

        document.getElementById(
            id
        ).textContent =
            value;
    }


    function formatMetricValue(
        value,
        unit
    ) {

        if (
            value === null
            || value === undefined
        ) {

            return '—';
        }


        return `${formatNumber(
            value
        )} ${unit}`;
    }


    function formatNumber(
        value
    ) {

        const number =
            Number(
                value
            );


        if (
            Number.isNaN(
                number
            )
        ) {

            return '0';
        }


        return number.toLocaleString(
            'es-MX',
            {
                maximumFractionDigits: 2
            }
        );
    }


    function formatDateOnly(
        value
    ) {

        if (!value) {
            return '—';
        }


        const parts =
            value.split('-');


        if (parts.length !== 3) {
            return value;
        }


        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }


    function formatDateTime(
        value
    ) {

        if (!value) {
            return '—';
        }


        return value;
    }


    function showReportMessage(
        message,
        type
    ) {

        reportMessage.className =
            type === 'success'
                ? 'report-message report-message-success'
                : 'report-message report-message-error';


        reportMessage.textContent =
            message;


        reportMessage.style.display =
            'block';
    }


    function hideReportMessage() {

        reportMessage.style.display =
            'none';
    }


    function escapeHtml(
        value
    ) {

        const div =
            document.createElement(
                'div'
            );


        div.textContent =
            value ?? '';


        return div.innerHTML;
    }


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    setDefaultDates();

    loadReportGreenhouses();

</script>

@endpush