<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>
        Reporte de monitoreo | LumaTek
    </title>

    <style>

        @page {
            margin: 28px 32px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                DejaVu Sans,
                sans-serif;

            color: #26372d;

            font-size: 10px;
            line-height: 1.45;
        }

        .header-table,
        .info-table,
        .metrics-table,
        .summary-table,
        .detail-table {
            width: 100%;

            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 18px;
        }

        .header-logo {
            width: 45%;
            vertical-align: middle;
        }

        .header-logo img {
            width: 155px;
            max-height: 55px;
        }

        .header-title {
            width: 55%;

            text-align: right;
            vertical-align: middle;
        }

        .header-title h1 {
            margin: 0;

            color: #153d2b;

            font-size: 19px;
        }

        .header-title p {
            margin: 5px 0 0;

            color: #748078;

            font-size: 9px;
        }

        .divider {
            height: 2px;

            margin-bottom: 18px;

            background: #176136;
        }

        .section {
            margin-bottom: 18px;
        }

        .section-title {
            margin: 0 0 9px;

            color: #153d2b;

            font-size: 13px;
        }

        .section-subtitle {
            margin: -4px 0 10px;

            color: #7b867f;

            font-size: 8px;
        }

        .info-table td {
            width: 33.33%;

            padding: 9px;

            border: 1px solid #dfe7e1;

            vertical-align: top;
        }

        .label {
            display: block;

            margin-bottom: 4px;

            color: #7d8981;

            font-size: 7px;

            text-transform: uppercase;
        }

        .value {
            color: #26372d;

            font-size: 9px;
            font-weight: bold;
        }

        .metrics-table {
            table-layout: fixed;
        }

        .metrics-table td {
            width: 33.33%;

            padding: 10px;

            border: 1px solid #dfe7e1;

            vertical-align: top;
        }

        .metric-name {
            margin-bottom: 8px;

            color: #176136;

            font-size: 10px;
            font-weight: bold;
        }

        .metric-average {
            margin-bottom: 9px;

            color: #153d2b;

            font-size: 18px;
            font-weight: bold;
        }

        .metric-small-table {
            width: 100%;

            border-collapse: collapse;
        }

        .metric-small-table td {
            width: 33.33%;

            padding: 5px 3px;

            border: 0;

            text-align: center;
        }

        .metric-small-label {
            display: block;

            color: #849087;

            font-size: 7px;
        }

        .metric-small-value {
            display: block;

            margin-top: 3px;

            color: #405248;

            font-size: 8px;
            font-weight: bold;
        }

        .summary-table {
            table-layout: fixed;
        }

        .summary-table td {
            width: 50%;

            padding: 10px;

            border: 1px solid #dfe7e1;

            vertical-align: top;
        }

        .summary-total {
            margin: 5px 0 10px;

            color: #153d2b;

            font-size: 20px;
            font-weight: bold;
        }

        .summary-detail {
            width: 100%;

            border-collapse: collapse;
        }

        .summary-detail td {
            width: 33.33%;

            padding: 5px;

            border: 0;

            text-align: center;
        }

        .summary-detail.two-columns td {
            width: 50%;
        }

        .summary-detail span {
            display: block;

            color: #849087;

            font-size: 7px;
        }

        .summary-detail strong {
            display: block;

            margin-top: 3px;

            color: #34483c;

            font-size: 8px;
        }

        .detail-table th {
            padding: 7px;

            border: 1px solid #dfe7e1;

            background: #edf4ef;

            color: #315040;

            text-align: left;

            font-size: 7px;
        }

        .detail-table td {
            padding: 7px;

            border: 1px solid #e5ebe7;

            color: #4b5b52;

            font-size: 7px;

            vertical-align: top;
        }

        .empty {
            padding: 12px;

            border: 1px solid #dfe7e1;

            background: #f8faf8;

            color: #7b867f;

            text-align: center;
        }

        .footer {
            margin-top: 22px;

            padding-top: 10px;

            border-top: 1px solid #dfe7e1;

            color: #8a948e;

            text-align: center;

            font-size: 7px;
        }

    </style>

</head>


<body>

{{-- =============================================================
     ENCABEZADO
============================================================= --}}

<table class="header-table">

    <tr>

        <td class="header-logo">

            <img
                src="{{ public_path('images/lumatek-logo.png') }}"
                alt="LumaTek"
            >

        </td>


        <td class="header-title">

            <h1>
                Reporte de monitoreo
            </h1>

            <p>
                Sistema LumaTek
            </p>

        </td>

    </tr>

</table>


<div class="divider"></div>


{{-- =============================================================
     INFORMACIÓN GENERAL
============================================================= --}}

<div class="section">

    <h2 class="section-title">
        Información general
    </h2>


    <table class="info-table">

        <tr>

            <td>

                <span class="label">
                    Empresa
                </span>

                <span class="value">
                    {{ $report['company']['name'] ?? '—' }}
                </span>

            </td>


            <td>

                <span class="label">
                    Invernadero
                </span>

                <span class="value">
                    {{ $report['greenhouse']['name'] ?? '—' }}
                </span>

            </td>


            <td>

                <span class="label">
                    Cultivo
                </span>

                <span class="value">
                    {{ $report['greenhouse']['crop_type'] ?? 'Sin especificar' }}
                </span>

            </td>

        </tr>


        <tr>

            <td>

                <span class="label">
                    Ubicación
                </span>

                <span class="value">
                    {{ $report['greenhouse']['location'] ?? 'Sin especificar' }}
                </span>

            </td>


            <td>

                <span class="label">
                    Periodo
                </span>

                <span class="value">

                    {{ $report['period']['start_date'] ?? '—' }}

                    a

                    {{ $report['period']['end_date'] ?? '—' }}

                </span>

            </td>


            <td>

                <span class="label">
                    Generado por
                </span>

                <span class="value">
                    {{ $report['generated_by']['name'] ?? '—' }}
                </span>

            </td>

        </tr>


        <tr>

            <td>

                <span class="label">
                    Área
                </span>

                <span class="value">

                    @if(
                        isset(
                            $report['greenhouse']['area']
                        )
                    )

                        {{ number_format(
                            $report['greenhouse']['area'],
                            2
                        ) }}
                        m²

                    @else

                        —

                    @endif

                </span>

            </td>


            <td>

                <span class="label">
                    Fecha de siembra
                </span>

                <span class="value">
                    {{ $report['greenhouse']['planting_date'] ?? '—' }}
                </span>

            </td>


            <td>

                <span class="label">
                    Fecha de generación
                </span>

                <span class="value">
                    {{ $report['generated_at'] ?? '—' }}
                </span>

            </td>

        </tr>

    </table>

</div>


{{-- =============================================================
     MONITOREO
============================================================= --}}

<div class="section">

    <h2 class="section-title">
        Resumen de monitoreo
    </h2>

    <p class="section-subtitle">
        Valores registrados durante el periodo seleccionado.
    </p>


    <table class="metrics-table">

        <tr>

            {{-- TEMPERATURA --}}

            <td>

                <div class="metric-name">
                    Temperatura
                </div>


                <div class="metric-average">

                    @if(
                        isset(
                            $report['metrics']['temperature']['average']
                        )
                    )

                        {{
                            number_format(
                                $report['metrics']['temperature']['average'],
                                2
                            )
                        }}
                        °C

                    @else

                        —

                    @endif

                </div>


                <table class="metric-small-table">

                    <tr>

                        <td>

                            <span class="metric-small-label">
                                Mínimo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['temperature']['minimum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['temperature']['minimum'],
                                            2
                                        )
                                    }}
                                    °C

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Máximo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['temperature']['maximum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['temperature']['maximum'],
                                            2
                                        )
                                    }}
                                    °C

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Lecturas
                            </span>

                            <span class="metric-small-value">

                                {{
                                    $report['metrics']['temperature']['total_readings']
                                    ?? 0
                                }}

                            </span>

                        </td>

                    </tr>

                </table>

            </td>


            {{-- HUMEDAD DEL SUELO --}}

            <td>

                <div class="metric-name">
                    Humedad del suelo
                </div>


                <div class="metric-average">

                    @if(
                        isset(
                            $report['metrics']['soil_humidity']['average']
                        )
                    )

                        {{
                            number_format(
                                $report['metrics']['soil_humidity']['average'],
                                2
                            )
                        }}
                        %

                    @else

                        —

                    @endif

                </div>


                <table class="metric-small-table">

                    <tr>

                        <td>

                            <span class="metric-small-label">
                                Mínimo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['soil_humidity']['minimum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['soil_humidity']['minimum'],
                                            2
                                        )
                                    }}
                                    %

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Máximo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['soil_humidity']['maximum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['soil_humidity']['maximum'],
                                            2
                                        )
                                    }}
                                    %

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Lecturas
                            </span>

                            <span class="metric-small-value">

                                {{
                                    $report['metrics']['soil_humidity']['total_readings']
                                    ?? 0
                                }}

                            </span>

                        </td>

                    </tr>

                </table>

            </td>


            {{-- HUMEDAD AMBIENTAL --}}

            <td>

                <div class="metric-name">
                    Humedad ambiental
                </div>


                <div class="metric-average">

                    @if(
                        isset(
                            $report['metrics']['ambient_humidity']['average']
                        )
                    )

                        {{
                            number_format(
                                $report['metrics']['ambient_humidity']['average'],
                                2
                            )
                        }}
                        %

                    @else

                        —

                    @endif

                </div>


                <table class="metric-small-table">

                    <tr>

                        <td>

                            <span class="metric-small-label">
                                Mínimo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['ambient_humidity']['minimum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['ambient_humidity']['minimum'],
                                            2
                                        )
                                    }}
                                    %

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Máximo
                            </span>

                            <span class="metric-small-value">

                                @if(
                                    isset(
                                        $report['metrics']['ambient_humidity']['maximum']
                                    )
                                )

                                    {{
                                        number_format(
                                            $report['metrics']['ambient_humidity']['maximum'],
                                            2
                                        )
                                    }}
                                    %

                                @else

                                    —

                                @endif

                            </span>

                        </td>


                        <td>

                            <span class="metric-small-label">
                                Lecturas
                            </span>

                            <span class="metric-small-value">

                                {{
                                    $report['metrics']['ambient_humidity']['total_readings']
                                    ?? 0
                                }}

                            </span>

                        </td>

                    </tr>

                </table>

            </td>

        </tr>

    </table>

</div>


{{-- =============================================================
     ALERTAS Y RIEGOS
============================================================= --}}

<div class="section">

    <h2 class="section-title">
        Actividad del periodo
    </h2>


    <table class="summary-table">

        <tr>

            {{-- ALERTAS --}}

            <td>

                <span class="label">
                    Alertas
                </span>

                <div class="summary-total">
                    {{
                        $report['alerts']['summary']['total']
                        ?? 0
                    }}
                </div>


                <table class="summary-detail">

                    <tr>

                        <td>

                            <span>
                                Activas
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['active']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Atendidas
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['acknowledged']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Resueltas
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['resolved']
                                    ?? 0
                                }}
                            </strong>

                        </td>

                    </tr>

                </table>


                <table class="summary-detail">

                    <tr>

                        <td>

                            <span>
                                Informativas
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['info']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Advertencias
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['warning']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Críticas
                            </span>

                            <strong>
                                {{
                                    $report['alerts']['summary']['critical']
                                    ?? 0
                                }}
                            </strong>

                        </td>

                    </tr>

                </table>

            </td>


            {{-- RIEGOS --}}

            <td>

                <span class="label">
                    Riegos
                </span>

                <div class="summary-total">
                    {{
                        $report['irrigation']['summary']['total']
                        ?? 0
                    }}
                </div>


                <table class="summary-detail">

                    <tr>

                        <td>

                            <span>
                                Manuales
                            </span>

                            <strong>
                                {{
                                    $report['irrigation']['summary']['manual']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Automáticos
                            </span>

                            <strong>
                                {{
                                    $report['irrigation']['summary']['automatic']
                                    ?? 0
                                }}
                            </strong>

                        </td>


                        <td>

                            <span>
                                Completados
                            </span>

                            <strong>
                                {{
                                    $report['irrigation']['summary']['completed']
                                    ?? 0
                                }}
                            </strong>

                        </td>

                    </tr>

                </table>


                <table class="summary-detail two-columns">

                    <tr>

                        <td>

                            <span>
                                Duración acumulada
                            </span>

                            <strong>
                                {{
                                    $report['irrigation']['summary']['total_duration_minutes']
                                    ?? 0
                                }}
                                min
                            </strong>

                        </td>


                        <td>

                            <span>
                                Agua registrada
                            </span>

                            <strong>
                                {{
                                    number_format(
                                        $report['irrigation']['summary']['total_water_liters']
                                        ?? 0,
                                        2
                                    )
                                }}
                                L
                            </strong>

                        </td>

                    </tr>

                </table>

            </td>

        </tr>

    </table>

</div>


{{-- =============================================================
     ALERTAS DEL PERIODO
============================================================= --}}

<div class="section">

    <h2 class="section-title">
        Alertas del periodo
    </h2>


    @if(
        !empty(
            $report['alerts']['recent']
        )
        &&
        count(
            $report['alerts']['recent']
        ) > 0
    )

        <table class="detail-table">

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


            <tbody>

                @foreach(
                    $report['alerts']['recent']
                    as $alert
                )

                    <tr>

                        <td>
                            {{ $alert['created_at'] ?? '—' }}
                        </td>

                        <td>
                            {{ $alert['type_label'] ?? 'Alerta' }}
                        </td>

                        <td>
                            {{ $alert['zone']['name'] ?? 'Sin zona' }}
                        </td>

                        <td>
                            {{ $alert['severity_label'] ?? '—' }}
                        </td>

                        <td>
                            {{ $alert['status_label'] ?? '—' }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @else

        <div class="empty">
            No se registraron alertas durante este periodo.
        </div>

    @endif

</div>


{{-- =============================================================
     RIEGOS DEL PERIODO
============================================================= --}}

<div class="section">

    <h2 class="section-title">
        Riegos del periodo
    </h2>


    @if(
        !empty(
            $report['irrigation']['recent']
        )
        &&
        count(
            $report['irrigation']['recent']
        ) > 0
    )

        <table class="detail-table">

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


            <tbody>

                @foreach(
                    $report['irrigation']['recent']
                    as $irrigation
                )

                    <tr>

                        <td>
                            {{ $irrigation['started_at'] ?? '—' }}
                        </td>

                        <td>
                            {{ $irrigation['zone']['name'] ?? 'Sin zona' }}
                        </td>

                        <td>
                            {{ $irrigation['mode_label'] ?? '—' }}
                        </td>

                        <td>

                            @if(
                                isset(
                                    $irrigation['duration_minutes']
                                )
                            )

                                {{
                                    $irrigation['duration_minutes']
                                }}
                                min

                            @else

                                —

                            @endif

                        </td>

                        <td>

                            @if(
                                isset(
                                    $irrigation['water_liters']
                                )
                            )

                                {{
                                    number_format(
                                        $irrigation['water_liters'],
                                        2
                                    )
                                }}
                                L

                            @else

                                —

                            @endif

                        </td>

                        <td>
                            {{ $irrigation['status_label'] ?? '—' }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @else

        <div class="empty">
            No se registraron riegos durante este periodo.
        </div>

    @endif

</div>


{{-- =============================================================
     PIE
============================================================= --}}

<div class="footer">

    LumaTek · Reporte generado automáticamente por el sistema.

</div>

</body>

</html>