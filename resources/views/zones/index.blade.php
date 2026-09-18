@extends('layouts.app')

@section('title', 'Zonas y sensores | LumaTek')

@section('page-title', 'Zonas y sensores')

@section(
    'page-subtitle',
    'Organiza las zonas, dispositivos y sensores de cada invernadero'
)

@section('content')

<div class="zones-page">

    {{-- =========================================================
         SELECTOR DE INVERNADERO
    ========================================================== --}}

    <section class="panel-card">

        <div class="panel-card-header">
            <div>
                <h2>Seleccionar invernadero</h2>
                <p>Elige el invernadero que deseas configurar.</p>
            </div>
        </div>

        <div class="panel-card-body">

            <div class="selector-row">

                <div class="panel-form-group">

                    <label for="greenhouse-select">
                        Invernadero
                    </label>

                    <select id="greenhouse-select">
                        <option value="">
                            Cargando invernaderos...
                        </option>
                    </select>

                </div>

                <div class="selected-info">

                    <span>Invernadero seleccionado</span>

                    <strong id="selected-greenhouse-name">
                        Ninguno
                    </strong>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         FORMULARIO DE ZONA
    ========================================================== --}}

    <section
        class="panel-card"
        style="margin-top:24px;"
    >

        <div class="panel-card-header">

            <div>

                <h2 id="zone-form-title">
                    Registrar zona
                </h2>

                <p>
                    Divide el invernadero en las áreas que necesites monitorear.
                </p>

            </div>

        </div>

        <div class="panel-card-body">

            <div
                id="zone-message"
                class="panel-message"
                style="display:none;"
            ></div>

            <form id="zone-form">

                <input
                    type="hidden"
                    id="zone-id"
                >

                <div class="panel-form-grid">

                    <div class="panel-form-group">

                        <label for="zone-name">
                            Nombre de la zona *
                        </label>

                        <input
                            type="text"
                            id="zone-name"
                            maxlength="120"
                            placeholder="Ej. Zona Norte"
                            required
                        >

                    </div>


                    <div class="panel-form-group">

                        <label for="zone-description">
                            Descripción
                        </label>

                        <input
                            type="text"
                            id="zone-description"
                            maxlength="500"
                            placeholder="Ej. Área principal de cultivo"
                        >

                    </div>


                    <div class="panel-form-group">

                        <label for="zone-position-x">
                            Posición horizontal
                        </label>

                        <input
                            type="number"
                            id="zone-position-x"
                            min="0"
                            max="100"
                            step="0.1"
                            placeholder="Ej. 25"
                        >

                        <span class="form-help">
                            Posición relativa de 0 a 100 %.
                        </span>

                    </div>


                    <div class="panel-form-group">

                        <label for="zone-position-y">
                            Posición vertical
                        </label>

                        <input
                            type="number"
                            id="zone-position-y"
                            min="0"
                            max="100"
                            step="0.1"
                            placeholder="Ej. 30"
                        >

                        <span class="form-help">
                            Posición relativa de 0 a 100 %.
                        </span>

                    </div>

                </div>

                <div class="panel-form-actions">

                    <button
                        type="button"
                        id="cancel-zone-edit"
                        class="btn-secondary"
                        style="display:none;"
                    >
                        Cancelar edición
                    </button>

                    <button
                        type="submit"
                        id="save-zone-button"
                        class="btn-primary"
                    >
                        Guardar zona
                    </button>

                </div>

            </form>

        </div>

    </section>


    {{-- =========================================================
         LISTADO DE ZONAS
    ========================================================== --}}

    <section
        class="panel-card"
        style="margin-top:24px;"
    >

        <div class="panel-card-header">

            <div>
                <h2>Zonas configuradas</h2>
                <p>Administra las zonas asociadas al invernadero.</p>
            </div>

            <span id="zones-count">
                0 zonas
            </span>

        </div>

        <div class="panel-card-body">

            <div
                id="zones-loading"
                class="empty-state"
                style="display:none;"
            >
                Cargando zonas...
            </div>

            <div
                id="zones-empty"
                class="empty-state"
                style="display:none;"
            >
                <div class="empty-icon">🌱</div>

                <strong>
                    No hay zonas configuradas
                </strong>

                <span>
                    Registra una zona para comenzar.
                </span>
            </div>

            <div
                id="zones-no-greenhouse"
                class="empty-state"
            >
                <div class="empty-icon">🏡</div>

                <strong>
                    Selecciona un invernadero
                </strong>

                <span>
                    Las zonas aparecerán aquí.
                </span>
            </div>

            <div
                id="zones-list"
                class="zones-grid"
            ></div>

        </div>

    </section>


    {{-- =========================================================
         DISPOSITIVOS
    ========================================================== --}}

    <section
        id="devices-section"
        class="panel-card"
        style="margin-top:24px; display:none;"
    >

        <div class="panel-card-header">

            <div>

                <h2>Dispositivos</h2>

                <p>
                    Administra los dispositivos instalados en
                    <strong id="device-zone-name"></strong>.
                </p>

            </div>

            <button
                type="button"
                id="close-devices"
                class="btn-secondary"
            >
                Cerrar
            </button>

        </div>

        <div class="panel-card-body">

            <div
                id="device-message"
                class="panel-message"
                style="display:none;"
            ></div>

            <div class="management-box">

                <h3 id="device-form-title">
                    Registrar dispositivo
                </h3>

                <form id="device-form">

                    <input
                        type="hidden"
                        id="device-id"
                    >

                    <div class="panel-form-grid">

                        <div class="panel-form-group">

                            <label for="device-name">
                                Nombre del dispositivo *
                            </label>

                            <input
                                type="text"
                                id="device-name"
                                maxlength="150"
                                placeholder="Ej. ESP32 Principal"
                                required
                            >

                        </div>


                        <div class="panel-form-group">

                            <label for="device-code">
                                Código *
                            </label>

                            <input
                                type="text"
                                id="device-code"
                                maxlength="100"
                                placeholder="Ej. ESP32-001"
                                required
                            >

                            <span class="form-help">
                                Debe ser único.
                            </span>

                        </div>


                        <div class="panel-form-group">

                            <label for="device-type">
                                Tipo de dispositivo *
                            </label>

                            <select
                                id="device-type"
                                required
                            >
                                <option value="">
                                    Selecciona un tipo
                                </option>

                                <option value="ESP32">
                                    ESP32
                                </option>

                                <option value="Gateway">
                                    Gateway
                                </option>

                                <option value="Controlador">
                                    Controlador
                                </option>

                                <option value="Nodo IoT">
                                    Nodo IoT
                                </option>

                                <option value="Otro">
                                    Otro
                                </option>
                            </select>

                        </div>


                        <div class="panel-form-group">

                            <label for="connection-type">
                                Tipo de conexión *
                            </label>

                            <select
                                id="connection-type"
                                required
                            >
                                <option value="">
                                    Selecciona una conexión
                                </option>

                                <option value="wifi">
                                    WiFi
                                </option>

                                <option value="ethernet">
                                    Ethernet
                                </option>

                                <option value="lora">
                                    LoRa
                                </option>

                                <option value="simulation">
                                    Simulación
                                </option>
                            </select>

                        </div>

                    </div>

                    <div class="panel-form-actions">

                        <button
                            type="button"
                            id="cancel-device-edit"
                            class="btn-secondary"
                            style="display:none;"
                        >
                            Cancelar edición
                        </button>

                        <button
                            type="submit"
                            id="save-device-button"
                            class="btn-primary"
                        >
                            Guardar dispositivo
                        </button>

                    </div>

                </form>

            </div>


            <div class="management-header">

                <div>
                    <h3>Dispositivos registrados</h3>
                    <p>Equipos asociados a esta zona.</p>
                </div>

                <span id="devices-count">
                    0 dispositivos
                </span>

            </div>

            <div
                id="devices-loading"
                class="empty-state"
                style="display:none;"
            >
                Cargando dispositivos...
            </div>

            <div
                id="devices-empty"
                class="empty-state"
                style="display:none;"
            >
                <div class="empty-icon">📡</div>

                <strong>No hay dispositivos</strong>

                <span>
                    Registra el primer dispositivo de esta zona.
                </span>
            </div>

            <div
                id="devices-list"
                class="devices-grid"
            ></div>

        </div>

    </section>


    {{-- =========================================================
         SENSORES
    ========================================================== --}}

    <section
        id="sensors-section"
        class="panel-card"
        style="margin-top:24px; display:none;"
    >

        <div class="panel-card-header">

            <div>

                <h2>Sensores</h2>

                <p>
                    Administra los sensores conectados a
                    <strong id="sensor-device-name"></strong>.
                </p>

            </div>

            <button
                type="button"
                id="close-sensors"
                class="btn-secondary"
            >
                Cerrar
            </button>

        </div>

        <div class="panel-card-body">

            <div
                id="sensor-message"
                class="panel-message"
                style="display:none;"
            ></div>


            <div class="management-box">

                <h3 id="sensor-form-title">
                    Registrar sensor
                </h3>

                <form id="sensor-form">

                    <input
                        type="hidden"
                        id="sensor-id"
                    >

                    <div class="panel-form-grid">

                        <div class="panel-form-group">

                            <label for="sensor-name">
                                Nombre del sensor *
                            </label>

                            <input
                                type="text"
                                id="sensor-name"
                                maxlength="150"
                                placeholder="Ej. Sensor temperatura norte"
                                required
                            >

                        </div>


                        <div class="panel-form-group">

                            <label for="sensor-code">
                                Código *
                            </label>

                            <input
                                type="text"
                                id="sensor-code"
                                maxlength="100"
                                placeholder="Ej. TEMP-NORTE-001"
                                required
                            >

                            <span class="form-help">
                                Debe ser único.
                            </span>

                        </div>


                        <div class="panel-form-group">

                            <label for="sensor-type">
                                Tipo de sensor *
                            </label>

                            <select
                                id="sensor-type"
                                required
                            >

                                <option value="">
                                    Selecciona un tipo
                                </option>

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

                            <span class="form-help">
                                La unidad se asigna automáticamente.
                            </span>

                        </div>


                        <div class="panel-form-group">

                            <label for="sensor-model">
                                Modelo
                            </label>

                            <input
                                type="text"
                                id="sensor-model"
                                maxlength="150"
                                placeholder="Ej. DHT22"
                            >

                        </div>


                        <div class="panel-form-group">

                            <label for="sensor-position-x">
                                Posición horizontal
                            </label>

                            <input
                                type="number"
                                id="sensor-position-x"
                                min="0"
                                max="100"
                                step="0.1"
                                placeholder="Ej. 35"
                            >

                        </div>


                        <div class="panel-form-group">

                            <label for="sensor-position-y">
                                Posición vertical
                            </label>

                            <input
                                type="number"
                                id="sensor-position-y"
                                min="0"
                                max="100"
                                step="0.1"
                                placeholder="Ej. 50"
                            >

                        </div>

                    </div>


                    <div class="panel-form-actions">

                        <button
                            type="button"
                            id="cancel-sensor-edit"
                            class="btn-secondary"
                            style="display:none;"
                        >
                            Cancelar edición
                        </button>

                        <button
                            type="submit"
                            id="save-sensor-button"
                            class="btn-primary"
                        >
                            Guardar sensor
                        </button>

                    </div>

                </form>

            </div>


            <div class="management-header">

                <div>

                    <h3>Sensores registrados</h3>

                    <p>
                        Sensores asociados a este dispositivo.
                    </p>

                </div>

                <span id="sensors-count">
                    0 sensores
                </span>

            </div>


            <div
                id="sensors-loading"
                class="empty-state"
                style="display:none;"
            >
                Cargando sensores...
            </div>


            <div
                id="sensors-empty"
                class="empty-state"
                style="display:none;"
            >

                <div class="empty-icon">🌡️</div>

                <strong>No hay sensores</strong>

                <span>
                    Registra el primer sensor de este dispositivo.
                </span>

            </div>


            <div
                id="sensors-list"
                class="sensors-grid"
            ></div>

        </div>

    </section>

</div>

@endsection


@push('styles')

<style>

    .zones-page {
        width: 100%;
    }

    .zones-page select {
        width: 100%;
        min-height: 44px;
        border: 1px solid #d9e3dc;
        border-radius: 9px;
        padding: 0 12px;
        background: #fff;
        color: #263d2e;
    }

    .selector-row {
        display: grid;
        grid-template-columns:
            minmax(260px, 1fr)
            minmax(220px, .7fr);
        gap: 20px;
        align-items: end;
    }

    .selected-info {
        min-height: 70px;
        padding: 14px 16px;
        background: #f5f9f6;
        border: 1px solid #e0e9e2;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
    }

    .selected-info span {
        color: #7b887f;
        font-size: 11px;
    }

    .selected-info strong {
        color: #183e29;
        font-size: 15px;
    }

    .empty-state {
        text-align: center;
        padding: 38px 20px;
        color: #728078;
    }

    .empty-state strong {
        display: block;
        margin-top: 8px;
        color: #3f5246;
        font-size: 14px;
    }

    .empty-state span {
        display: block;
        margin-top: 5px;
        font-size: 12px;
    }

    .empty-icon {
        font-size: 30px;
    }

    .zones-grid,
    .devices-grid,
    .sensors-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }

    .zone-card,
    .device-card,
    .sensor-card {
        border: 1px solid #e0e8e2;
        border-radius: 13px;
        background: #fff;
        padding: 17px;
    }

    .zone-card.inactive,
    .device-card.inactive,
    .sensor-card.inactive {
        opacity: .7;
        background: #fafafa;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .card-title {
        margin: 0;
        color: #173d27;
        font-size: 16px;
    }

    .card-code {
        display: inline-block;
        margin-top: 5px;
        color: #748078;
        font-family: monospace;
        font-size: 10px;
    }

    .status-badge {
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-active {
        background: #eaf7ee;
        color: #176136;
    }

    .status-inactive {
        background: #eeeeee;
        color: #6b746e;
    }

    .description {
        margin: 10px 0 15px;
        min-height: 30px;
        color: #748078;
        font-size: 11px;
        line-height: 1.5;
    }

    .info-list {
        display: grid;
        gap: 8px;
        margin-top: 14px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 11px;
    }

    .info-row span {
        color: #7d8981;
    }

    .info-row strong {
        color: #35473c;
        text-align: right;
    }

    .summary-box {
        margin-top: 14px;
        padding: 11px;
        border-radius: 9px;
        background: #f5f9f6;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .summary-box span {
        color: #6e7c73;
        font-size: 11px;
    }

    .summary-box strong {
        color: #176136;
    }

    .actions-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .action-button {
        border: 1px solid #dce5de;
        border-radius: 8px;
        background: #fff;
        padding: 8px 11px;
        cursor: pointer;
        color: #3e5547;
        font-size: 11px;
        font-weight: 600;
    }

    .action-button:hover {
        background: #f3f8f4;
    }

    .action-main {
        background: #edf8f0;
        border-color: #cfe5d4;
        color: #176136;
    }

    .action-warning {
        color: #a05c25;
    }

    .management-box {
        padding: 18px;
        border: 1px solid #e1e9e3;
        border-radius: 12px;
        background: #f9fbfa;
    }

    .management-box h3 {
        margin: 0 0 18px;
        color: #173d27;
        font-size: 15px;
    }

    .management-header {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e7ece8;
        display: flex;
        justify-content: space-between;
        gap: 15px;
        align-items: flex-start;
    }

    .management-header h3 {
        margin: 0;
        color: #173d27;
        font-size: 15px;
    }

    .management-header p {
        margin: 4px 0 0;
        color: #778279;
        font-size: 11px;
    }

    .devices-grid,
    .sensors-grid {
        margin-top: 18px;
    }

    .latest-reading {
        margin-top: 14px;
        padding: 11px;
        border-radius: 9px;
        background: #f3f8f4;
    }

    .latest-reading-label {
        display: block;
        color: #718077;
        font-size: 10px;
    }

    .latest-reading strong {
        display: block;
        margin-top: 4px;
        color: #176136;
        font-size: 19px;
    }

    .latest-reading small {
        display: block;
        margin-top: 3px;
        color: #7c8880;
        font-size: 9px;
    }

    @media (max-width:760px) {

        .selector-row {
            grid-template-columns: 1fr;
        }

    }

</style>

@endpush


@push('scripts')

<script>

    const token =
        sessionStorage.getItem('lumatek_access_token');


    if (!token) {
        window.location.href = '/login';
    }


    const greenhouseSelect =
        document.getElementById('greenhouse-select');

    const selectedGreenhouseName =
        document.getElementById('selected-greenhouse-name');


    const zoneForm =
        document.getElementById('zone-form');

    const zoneId =
        document.getElementById('zone-id');

    const zoneName =
        document.getElementById('zone-name');

    const zoneDescription =
        document.getElementById('zone-description');

    const zonePositionX =
        document.getElementById('zone-position-x');

    const zonePositionY =
        document.getElementById('zone-position-y');

    const zoneFormTitle =
        document.getElementById('zone-form-title');

    const saveZoneButton =
        document.getElementById('save-zone-button');

    const cancelZoneEdit =
        document.getElementById('cancel-zone-edit');

    const zoneMessage =
        document.getElementById('zone-message');

    const zonesList =
        document.getElementById('zones-list');

    const zonesLoading =
        document.getElementById('zones-loading');

    const zonesEmpty =
        document.getElementById('zones-empty');

    const zonesNoGreenhouse =
        document.getElementById('zones-no-greenhouse');

    const zonesCount =
        document.getElementById('zones-count');


    const devicesSection =
        document.getElementById('devices-section');

    const deviceZoneName =
        document.getElementById('device-zone-name');

    const closeDevices =
        document.getElementById('close-devices');

    const deviceForm =
        document.getElementById('device-form');

    const deviceId =
        document.getElementById('device-id');

    const deviceName =
        document.getElementById('device-name');

    const deviceCode =
        document.getElementById('device-code');

    const deviceType =
        document.getElementById('device-type');

    const connectionType =
        document.getElementById('connection-type');

    const deviceFormTitle =
        document.getElementById('device-form-title');

    const saveDeviceButton =
        document.getElementById('save-device-button');

    const cancelDeviceEdit =
        document.getElementById('cancel-device-edit');

    const deviceMessage =
        document.getElementById('device-message');

    const devicesList =
        document.getElementById('devices-list');

    const devicesLoading =
        document.getElementById('devices-loading');

    const devicesEmpty =
        document.getElementById('devices-empty');

    const devicesCount =
        document.getElementById('devices-count');


    const sensorsSection =
        document.getElementById('sensors-section');

    const sensorDeviceName =
        document.getElementById('sensor-device-name');

    const closeSensors =
        document.getElementById('close-sensors');

    const sensorForm =
        document.getElementById('sensor-form');

    const sensorId =
        document.getElementById('sensor-id');

    const sensorName =
        document.getElementById('sensor-name');

    const sensorCode =
        document.getElementById('sensor-code');

    const sensorType =
        document.getElementById('sensor-type');

    const sensorModel =
        document.getElementById('sensor-model');

    const sensorPositionX =
        document.getElementById('sensor-position-x');

    const sensorPositionY =
        document.getElementById('sensor-position-y');

    const sensorFormTitle =
        document.getElementById('sensor-form-title');

    const saveSensorButton =
        document.getElementById('save-sensor-button');

    const cancelSensorEdit =
        document.getElementById('cancel-sensor-edit');

    const sensorMessage =
        document.getElementById('sensor-message');

    const sensorsList =
        document.getElementById('sensors-list');

    const sensorsLoading =
        document.getElementById('sensors-loading');

    const sensorsEmpty =
        document.getElementById('sensors-empty');

    const sensorsCount =
        document.getElementById('sensors-count');


    let greenhouses = [];

    let selectedDeviceZone = null;

    let selectedSensorDevice = null;


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


    function logoutIfUnauthorized(response) {

        if (response.status === 401) {

            sessionStorage.clear();

            window.location.href =
                '/login';

            return true;
        }

        return false;
    }


    function showMessage(
        element,
        text,
        type = 'success'
    ) {

        element.className =
            'panel-message ' +
            (
                type === 'success'
                    ? 'panel-message-success'
                    : 'panel-message-error'
            );

        element.textContent =
            text;

        element.style.display =
            'block';
    }


    function hideMessage(element) {

        element.style.display =
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
                                `Bearer ${token}`
                        }
                    }
                );


            if (logoutIfUnauthorized(response)) {
                return;
            }


            const result =
                await response.json();


            greenhouses =
                result.data ?? [];


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


            if (greenhouses.length === 1) {

                greenhouseSelect.value =
                    greenhouses[0].id;

                greenhouseSelect.dispatchEvent(
                    new Event('change')
                );
            }


        } catch (error) {

            greenhouseSelect.innerHTML =
                `
                    <option value="">
                        Error al cargar invernaderos
                    </option>
                `;
        }
    }


    greenhouseSelect.addEventListener(
        'change',
        function () {

            resetZoneForm();

            closeDeviceManager();

            hideMessage(zoneMessage);


            const id =
                greenhouseSelect.value;


            if (!id) {

                selectedGreenhouseName.textContent =
                    'Ninguno';

                zonesList.innerHTML =
                    '';

                zonesNoGreenhouse.style.display =
                    'block';

                zonesEmpty.style.display =
                    'none';

                zonesCount.textContent =
                    '0 zonas';

                return;
            }


            const greenhouse =
                greenhouses.find(
                    item =>
                        String(item.id) ===
                        String(id)
                );


            selectedGreenhouseName.textContent =
                greenhouse?.name
                ?? 'Invernadero';


            loadZones();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ZONAS
    |--------------------------------------------------------------------------
    */

    async function loadZones() {

        const greenhouseId =
            greenhouseSelect.value;


        if (!greenhouseId) {
            return;
        }


        zonesLoading.style.display =
            'block';

        zonesEmpty.style.display =
            'none';

        zonesNoGreenhouse.style.display =
            'none';

        zonesList.innerHTML =
            '';


        try {

            const response =
                await fetch(
                    `/api/greenhouses/${greenhouseId}/zones`,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (logoutIfUnauthorized(response)) {
                return;
            }


            const result =
                await response.json();


            const zones =
                result.data ?? [];


            zonesLoading.style.display =
                'none';


            zonesCount.textContent =
                `${zones.length} zona${zones.length === 1 ? '' : 's'}`;


            if (!zones.length) {

                zonesEmpty.style.display =
                    'block';

                return;
            }


            zones.forEach(
                zone =>
                    createZoneCard(zone)
            );


        } catch (error) {

            zonesLoading.style.display =
                'none';

            showMessage(
                zoneMessage,
                'No fue posible cargar las zonas.',
                'error'
            );
        }
    }


    function createZoneCard(zone) {

        const card =
            document.createElement('article');


        card.className =
            'zone-card';


        if (zone.status === 'inactive') {
            card.classList.add('inactive');
        }


        const x =
            zone.position_x !== null
                ? `${zone.position_x} %`
                : 'Sin definir';


        const y =
            zone.position_y !== null
                ? `${zone.position_y} %`
                : 'Sin definir';


        card.innerHTML = `

            <div class="card-header">

                <h3 class="card-title">
                    ${escapeHtml(zone.name)}
                </h3>

                <span
                    class="
                        status-badge
                        ${
                            zone.status === 'active'
                                ? 'status-active'
                                : 'status-inactive'
                        }
                    "
                >
                    ${
                        zone.status === 'active'
                            ? 'Activa'
                            : 'Inactiva'
                    }
                </span>

            </div>


            <div class="description">
                ${escapeHtml(
                    zone.description
                    ?? 'Sin descripción registrada.'
                )}
            </div>


            <div class="info-list">

                <div class="info-row">
                    <span>Posición X</span>
                    <strong>${escapeHtml(x)}</strong>
                </div>

                <div class="info-row">
                    <span>Posición Y</span>
                    <strong>${escapeHtml(y)}</strong>
                </div>

            </div>


            <div class="summary-box">

                <span>
                    Dispositivos asociados
                </span>

                <strong>
                    ${zone.devices_count ?? 0}
                </strong>

            </div>


            <div class="actions-row">

                <button
                    type="button"
                    class="action-button action-main manage-devices"
                >
                    Dispositivos
                </button>

                <button
                    type="button"
                    class="action-button edit-zone"
                >
                    Editar
                </button>

                <button
                    type="button"
                    class="action-button action-warning status-zone"
                >
                    ${
                        zone.status === 'active'
                            ? 'Desactivar'
                            : 'Activar'
                    }
                </button>

            </div>
        `;


        card
            .querySelector('.manage-devices')
            .addEventListener(
                'click',
                () => openDeviceManager(zone)
            );


        card
            .querySelector('.edit-zone')
            .addEventListener(
                'click',
                () => startEditZone(zone)
            );


        card
            .querySelector('.status-zone')
            .addEventListener(
                'click',
                () => changeZoneStatus(zone)
            );


        zonesList.appendChild(card);
    }


    zoneForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            const greenhouseId =
                greenhouseSelect.value;


            if (!greenhouseId) {

                showMessage(
                    zoneMessage,
                    'Selecciona primero un invernadero.',
                    'error'
                );

                return;
            }


            hideMessage(zoneMessage);


            const editing =
                Boolean(zoneId.value);


            const payload = {

                name:
                    zoneName.value.trim(),

                description:
                    zoneDescription.value.trim()
                    || null,

                position_x:
                    zonePositionX.value !== ''
                        ? Number(zonePositionX.value)
                        : null,

                position_y:
                    zonePositionY.value !== ''
                        ? Number(zonePositionY.value)
                        : null

            };


            const url =
                editing
                    ? `/api/zones/${zoneId.value}`
                    : `/api/greenhouses/${greenhouseId}/zones`;


            const method =
                editing
                    ? 'PUT'
                    : 'POST';


            try {

                const response =
                    await fetch(
                        url,
                        {
                            method,

                            headers: {
                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${token}`
                            },

                            body:
                                JSON.stringify(payload)
                        }
                    );


                const result =
                    await response.json();


                if (logoutIfUnauthorized(response)) {
                    return;
                }


                if (!response.ok) {

                    const errors =
                        result.errors
                            ? Object.values(
                                result.errors
                            ).flat().join(' ')
                            : result.message;


                    showMessage(
                        zoneMessage,
                        errors
                        ?? 'No fue posible guardar la zona.',
                        'error'
                    );

                    return;
                }


                resetZoneForm();


                showMessage(
                    zoneMessage,
                    result.message
                );


                await loadZones();


            } catch (error) {

                showMessage(
                    zoneMessage,
                    'No fue posible conectar con el servidor.',
                    'error'
                );
            }
        }
    );


    function startEditZone(zone) {

        zoneId.value =
            zone.id;

        zoneName.value =
            zone.name ?? '';

        zoneDescription.value =
            zone.description ?? '';

        zonePositionX.value =
            zone.position_x ?? '';

        zonePositionY.value =
            zone.position_y ?? '';


        zoneFormTitle.textContent =
            'Editar zona';

        saveZoneButton.textContent =
            'Guardar cambios';

        cancelZoneEdit.style.display =
            'inline-flex';


        window.scrollTo({
            top: 250,
            behavior: 'smooth'
        });
    }


    function resetZoneForm() {

        zoneForm.reset();

        zoneId.value =
            '';

        zoneFormTitle.textContent =
            'Registrar zona';

        saveZoneButton.textContent =
            'Guardar zona';

        cancelZoneEdit.style.display =
            'none';
    }


    cancelZoneEdit.addEventListener(
        'click',
        resetZoneForm
    );


    async function changeZoneStatus(zone) {

        const newStatus =
            zone.status === 'active'
                ? 'inactive'
                : 'active';


        if (
            !confirm(
                `¿Deseas ${
                    newStatus === 'active'
                        ? 'activar'
                        : 'desactivar'
                } "${zone.name}"?`
            )
        ) {
            return;
        }


        const response =
            await fetch(
                `/api/zones/${zone.id}/status`,
                {
                    method:
                        'PATCH',

                    headers: {
                        'Accept':
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'Authorization':
                            `Bearer ${token}`
                    },

                    body:
                        JSON.stringify({
                            status:
                                newStatus
                        })
                }
            );


        const result =
            await response.json();


        if (!response.ok) {

            showMessage(
                zoneMessage,
                result.message,
                'error'
            );

            return;
        }


        showMessage(
            zoneMessage,
            result.message
        );


        closeDeviceManager();

        loadZones();
    }


    /*
    |--------------------------------------------------------------------------
    | DISPOSITIVOS
    |--------------------------------------------------------------------------
    */

    function openDeviceManager(zone) {

        selectedDeviceZone =
            zone;


        closeSensorManager();


        deviceZoneName.textContent =
            zone.name;


        devicesSection.style.display =
            'block';


        resetDeviceForm();

        hideMessage(deviceMessage);


        loadDevices();


        devicesSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }


    function closeDeviceManager() {

        selectedDeviceZone =
            null;

        devicesSection.style.display =
            'none';

        devicesList.innerHTML =
            '';

        resetDeviceForm();

        closeSensorManager();
    }


    closeDevices.addEventListener(
        'click',
        closeDeviceManager
    );


    async function loadDevices() {

        if (!selectedDeviceZone) {
            return;
        }


        devicesLoading.style.display =
            'block';

        devicesEmpty.style.display =
            'none';

        devicesList.innerHTML =
            '';


        try {

            const response =
                await fetch(
                    `/api/zones/${selectedDeviceZone.id}/devices`,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (logoutIfUnauthorized(response)) {
                return;
            }


            const result =
                await response.json();


            const devices =
                result.data ?? [];


            devicesLoading.style.display =
                'none';


            devicesCount.textContent =
                `${devices.length} dispositivo${devices.length === 1 ? '' : 's'}`;


            if (!devices.length) {

                devicesEmpty.style.display =
                    'block';

                return;
            }


            devices.forEach(
                device =>
                    createDeviceCard(device)
            );


        } catch (error) {

            devicesLoading.style.display =
                'none';

            showMessage(
                deviceMessage,
                'No fue posible cargar los dispositivos.',
                'error'
            );
        }
    }


    function createDeviceCard(device) {

        const card =
            document.createElement('article');


        card.className =
            'device-card';


        if (device.status === 'inactive') {
            card.classList.add('inactive');
        }


        const connectionLabels = {
            wifi: 'WiFi',
            ethernet: 'Ethernet',
            lora: 'LoRa',
            simulation: 'Simulación'
        };


        card.innerHTML = `

            <div class="card-header">

                <div>

                    <h3 class="card-title">
                        ${escapeHtml(device.name)}
                    </h3>

                    <span class="card-code">
                        ${escapeHtml(device.device_code)}
                    </span>

                </div>


                <span
                    class="
                        status-badge
                        ${
                            device.status === 'active'
                                ? 'status-active'
                                : 'status-inactive'
                        }
                    "
                >
                    ${
                        device.status === 'active'
                            ? 'Activo'
                            : 'Inactivo'
                    }
                </span>

            </div>


            <div class="info-list">

                <div class="info-row">

                    <span>Tipo</span>

                    <strong>
                        ${escapeHtml(device.device_type)}
                    </strong>

                </div>


                <div class="info-row">

                    <span>Conexión</span>

                    <strong>
                        ${
                            connectionLabels[
                                device.connection_type
                            ]
                            ?? escapeHtml(
                                device.connection_type
                            )
                        }
                    </strong>

                </div>

            </div>


            <div class="summary-box">

                <span>
                    Sensores asociados
                </span>

                <strong>
                    ${device.sensors_count ?? 0}
                </strong>

            </div>


            <div class="actions-row">

                <button
                    type="button"
                    class="action-button action-main sensors-device"
                >
                    Sensores
                </button>

                <button
                    type="button"
                    class="action-button edit-device"
                >
                    Editar
                </button>

                <button
                    type="button"
                    class="action-button action-warning status-device"
                >
                    ${
                        device.status === 'active'
                            ? 'Desactivar'
                            : 'Activar'
                    }
                </button>

            </div>
        `;


        card
            .querySelector('.sensors-device')
            .addEventListener(
                'click',
                () =>
                    openSensorManager(device)
            );


        card
            .querySelector('.edit-device')
            .addEventListener(
                'click',
                () =>
                    startEditDevice(device)
            );


        card
            .querySelector('.status-device')
            .addEventListener(
                'click',
                () =>
                    changeDeviceStatus(device)
            );


        devicesList.appendChild(card);
    }


    deviceForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            if (!selectedDeviceZone) {
                return;
            }


            hideMessage(deviceMessage);


            const editing =
                Boolean(deviceId.value);


            const payload = {

                name:
                    deviceName.value.trim(),

                device_code:
                    deviceCode.value
                        .trim()
                        .toUpperCase(),

                device_type:
                    deviceType.value,

                connection_type:
                    connectionType.value
            };


            const url =
                editing
                    ? `/api/devices/${deviceId.value}`
                    : `/api/zones/${selectedDeviceZone.id}/devices`;


            const method =
                editing
                    ? 'PUT'
                    : 'POST';


            try {

                const response =
                    await fetch(
                        url,
                        {
                            method,

                            headers: {
                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${token}`
                            },

                            body:
                                JSON.stringify(payload)
                        }
                    );


                const result =
                    await response.json();


                if (!response.ok) {

                    const errors =
                        result.errors
                            ? Object.values(
                                result.errors
                            ).flat().join(' ')
                            : result.message;


                    showMessage(
                        deviceMessage,
                        errors
                        ?? 'No fue posible guardar el dispositivo.',
                        'error'
                    );

                    return;
                }


                resetDeviceForm();


                showMessage(
                    deviceMessage,
                    result.message
                );


                await loadDevices();

                await loadZones();


            } catch (error) {

                showMessage(
                    deviceMessage,
                    'No fue posible conectar con el servidor.',
                    'error'
                );
            }
        }
    );


    function startEditDevice(device) {

        deviceId.value =
            device.id;

        deviceName.value =
            device.name ?? '';

        deviceCode.value =
            device.device_code ?? '';

        deviceType.value =
            device.device_type ?? '';

        connectionType.value =
            device.connection_type ?? '';


        deviceFormTitle.textContent =
            'Editar dispositivo';

        saveDeviceButton.textContent =
            'Guardar cambios';

        cancelDeviceEdit.style.display =
            'inline-flex';


        devicesSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }


    function resetDeviceForm() {

        deviceForm.reset();

        deviceId.value =
            '';

        deviceFormTitle.textContent =
            'Registrar dispositivo';

        saveDeviceButton.textContent =
            'Guardar dispositivo';

        cancelDeviceEdit.style.display =
            'none';
    }


    cancelDeviceEdit.addEventListener(
        'click',
        resetDeviceForm
    );


    async function changeDeviceStatus(device) {

        const newStatus =
            device.status === 'active'
                ? 'inactive'
                : 'active';


        if (
            !confirm(
                `¿Deseas ${
                    newStatus === 'active'
                        ? 'activar'
                        : 'desactivar'
                } "${device.name}"?`
            )
        ) {
            return;
        }


        const response =
            await fetch(
                `/api/devices/${device.id}/status`,
                {
                    method: 'PATCH',

                    headers: {
                        'Accept':
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'Authorization':
                            `Bearer ${token}`
                    },

                    body:
                        JSON.stringify({
                            status:
                                newStatus
                        })
                }
            );


        const result =
            await response.json();


        if (!response.ok) {

            showMessage(
                deviceMessage,
                result.message,
                'error'
            );

            return;
        }


        showMessage(
            deviceMessage,
            result.message
        );


        if (
            selectedSensorDevice?.id ===
            device.id
        ) {
            closeSensorManager();
        }


        loadDevices();
    }


    /*
    |--------------------------------------------------------------------------
    | SENSORES
    |--------------------------------------------------------------------------
    */

    function openSensorManager(device) {

        selectedSensorDevice =
            device;


        sensorDeviceName.textContent =
            device.name;


        sensorsSection.style.display =
            'block';


        resetSensorForm();

        hideMessage(sensorMessage);


        loadSensors();


        sensorsSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }


    function closeSensorManager() {

        selectedSensorDevice =
            null;

        sensorsSection.style.display =
            'none';

        sensorsList.innerHTML =
            '';

        resetSensorForm();
    }


    closeSensors.addEventListener(
        'click',
        closeSensorManager
    );


    async function loadSensors() {

        if (!selectedSensorDevice) {
            return;
        }


        sensorsLoading.style.display =
            'block';

        sensorsEmpty.style.display =
            'none';

        sensorsList.innerHTML =
            '';


        try {

            const response =
                await fetch(
                    `/api/devices/${selectedSensorDevice.id}/sensors`,
                    {
                        headers: {
                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        }
                    }
                );


            if (logoutIfUnauthorized(response)) {
                return;
            }


            const result =
                await response.json();


            const sensors =
                result.data ?? [];


            sensorsLoading.style.display =
                'none';


            sensorsCount.textContent =
                `${sensors.length} sensor${sensors.length === 1 ? '' : 'es'}`;


            if (!sensors.length) {

                sensorsEmpty.style.display =
                    'block';

                return;
            }


            sensors.forEach(
                sensor =>
                    createSensorCard(sensor)
            );


        } catch (error) {

            sensorsLoading.style.display =
                'none';


            showMessage(
                sensorMessage,
                'No fue posible cargar los sensores.',
                'error'
            );
        }
    }


    function createSensorCard(sensor) {

        const card =
            document.createElement('article');


        card.className =
            'sensor-card';


        if (sensor.status === 'inactive') {
            card.classList.add('inactive');
        }


        const typeLabels = {

            temperature:
                'Temperatura',

            soil_humidity:
                'Humedad del suelo',

            ambient_humidity:
                'Humedad ambiental'

        };


        const positionX =
            sensor.position_x !== null
            && sensor.position_x !== undefined
                ? `${sensor.position_x} %`
                : 'Sin definir';


        const positionY =
            sensor.position_y !== null
            && sensor.position_y !== undefined
                ? `${sensor.position_y} %`
                : 'Sin definir';


        let latestReadingHtml =
            `
                <div class="latest-reading">

                    <span class="latest-reading-label">
                        Última lectura
                    </span>

                    <strong>
                        Sin lecturas
                    </strong>

                </div>
            `;


        if (sensor.latest_reading) {

            const reading =
                sensor.latest_reading;


            latestReadingHtml =
                `
                    <div class="latest-reading">

                        <span class="latest-reading-label">
                            Última lectura
                        </span>

                        <strong>
                            ${Number(
                                reading.value
                            ).toFixed(1)}
                            ${escapeHtml(sensor.unit)}
                        </strong>

                        <small>
                            ${escapeHtml(
                                reading.recorded_at
                            )}
                        </small>

                    </div>
                `;
        }


        card.innerHTML = `

            <div class="card-header">

                <div>

                    <h3 class="card-title">
                        ${escapeHtml(sensor.name)}
                    </h3>

                    <span class="card-code">
                        ${escapeHtml(sensor.sensor_code)}
                    </span>

                </div>


                <span
                    class="
                        status-badge
                        ${
                            sensor.status === 'active'
                                ? 'status-active'
                                : 'status-inactive'
                        }
                    "
                >
                    ${
                        sensor.status === 'active'
                            ? 'Activo'
                            : 'Inactivo'
                    }
                </span>

            </div>


            <div class="info-list">

                <div class="info-row">

                    <span>Tipo</span>

                    <strong>
                        ${
                            typeLabels[
                                sensor.sensor_type
                            ]
                            ?? escapeHtml(
                                sensor.sensor_type
                            )
                        }
                    </strong>

                </div>


                <div class="info-row">

                    <span>Unidad</span>

                    <strong>
                        ${escapeHtml(sensor.unit)}
                    </strong>

                </div>


                <div class="info-row">

                    <span>Modelo</span>

                    <strong>
                        ${escapeHtml(
                            sensor.model
                            ?? 'Sin definir'
                        )}
                    </strong>

                </div>


                <div class="info-row">

                    <span>Posición X</span>

                    <strong>
                        ${escapeHtml(positionX)}
                    </strong>

                </div>


                <div class="info-row">

                    <span>Posición Y</span>

                    <strong>
                        ${escapeHtml(positionY)}
                    </strong>

                </div>

            </div>


            ${latestReadingHtml}


            <div class="actions-row">

                <button
                    type="button"
                    class="action-button edit-sensor"
                >
                    Editar
                </button>


                <button
                    type="button"
                    class="action-button action-warning status-sensor"
                >
                    ${
                        sensor.status === 'active'
                            ? 'Desactivar'
                            : 'Activar'
                    }
                </button>

            </div>
        `;


        card
            .querySelector('.edit-sensor')
            .addEventListener(
                'click',
                () =>
                    startEditSensor(sensor)
            );


        card
            .querySelector('.status-sensor')
            .addEventListener(
                'click',
                () =>
                    changeSensorStatus(sensor)
            );


        sensorsList.appendChild(card);
    }


    sensorForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            if (!selectedSensorDevice) {
                return;
            }


            hideMessage(sensorMessage);


            const editing =
                Boolean(sensorId.value);


            const payload = {

                name:
                    sensorName.value.trim(),

                sensor_code:
                    sensorCode.value
                        .trim()
                        .toUpperCase(),

                sensor_type:
                    sensorType.value,

                model:
                    sensorModel.value.trim()
                    || null,

                position_x:
                    sensorPositionX.value !== ''
                        ? Number(
                            sensorPositionX.value
                        )
                        : null,

                position_y:
                    sensorPositionY.value !== ''
                        ? Number(
                            sensorPositionY.value
                        )
                        : null
            };


            const url =
                editing
                    ? `/api/sensors/${sensorId.value}`
                    : `/api/devices/${selectedSensorDevice.id}/sensors`;


            const method =
                editing
                    ? 'PUT'
                    : 'POST';


            try {

                const response =
                    await fetch(
                        url,
                        {
                            method,

                            headers: {
                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${token}`
                            },

                            body:
                                JSON.stringify(payload)
                        }
                    );


                const result =
                    await response.json();


                if (logoutIfUnauthorized(response)) {
                    return;
                }


                if (!response.ok) {

                    const errors =
                        result.errors
                            ? Object.values(
                                result.errors
                            ).flat().join(' ')
                            : result.message;


                    showMessage(
                        sensorMessage,
                        errors
                        ?? 'No fue posible guardar el sensor.',
                        'error'
                    );

                    return;
                }


                resetSensorForm();


                showMessage(
                    sensorMessage,
                    result.message
                );


                await loadSensors();

                await loadDevices();


            } catch (error) {

                showMessage(
                    sensorMessage,
                    'No fue posible conectar con el servidor.',
                    'error'
                );
            }
        }
    );


    function startEditSensor(sensor) {

        sensorId.value =
            sensor.id;

        sensorName.value =
            sensor.name ?? '';

        sensorCode.value =
            sensor.sensor_code ?? '';

        sensorType.value =
            sensor.sensor_type ?? '';

        sensorModel.value =
            sensor.model ?? '';

        sensorPositionX.value =
            sensor.position_x ?? '';

        sensorPositionY.value =
            sensor.position_y ?? '';


        sensorFormTitle.textContent =
            'Editar sensor';

        saveSensorButton.textContent =
            'Guardar cambios';

        cancelSensorEdit.style.display =
            'inline-flex';


        sensorsSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }


    function resetSensorForm() {

        sensorForm.reset();

        sensorId.value =
            '';

        sensorFormTitle.textContent =
            'Registrar sensor';

        saveSensorButton.textContent =
            'Guardar sensor';

        cancelSensorEdit.style.display =
            'none';
    }


    cancelSensorEdit.addEventListener(
        'click',
        resetSensorForm
    );


    async function changeSensorStatus(sensor) {

        const newStatus =
            sensor.status === 'active'
                ? 'inactive'
                : 'active';


        if (
            !confirm(
                `¿Deseas ${
                    newStatus === 'active'
                        ? 'activar'
                        : 'desactivar'
                } "${sensor.name}"?`
            )
        ) {
            return;
        }


        try {

            const response =
                await fetch(
                    `/api/sensors/${sensor.id}/status`,
                    {
                        method:
                            'PATCH',

                        headers: {
                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'Authorization':
                                `Bearer ${token}`
                        },

                        body:
                            JSON.stringify({
                                status:
                                    newStatus
                            })
                    }
                );


            const result =
                await response.json();


            if (!response.ok) {

                showMessage(
                    sensorMessage,
                    result.message
                    ?? 'No fue posible cambiar el estado del sensor.',
                    'error'
                );

                return;
            }


            showMessage(
                sensorMessage,
                result.message
            );


            await loadSensors();


        } catch (error) {

            showMessage(
                sensorMessage,
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    loadGreenhouses();

</script>

@endpush