<section class="irrigation-card">

    <div class="section-header">

        <div>
            <h2>
                Riego automático
            </h2>

            <p>
                Configura la automatización de riego para cada zona.
            </p>
        </div>

        <span
            id="automatic-status-badge"
            class="automatic-status-badge automatic-disabled"
        >
            Desactivado
        </span>

    </div>


    <div class="automatic-description">

        <strong>
            Funcionamiento
        </strong>

        <p>
            Cuando la humedad del suelo se encuentra por debajo
            del mínimo configurado para el invernadero,
            LumaTek puede iniciar automáticamente un evento de riego.
        </p>

    </div>


    <form id="automatic-irrigation-form">

        <div class="form-grid">

            <div class="form-group">

                <label for="automatic-greenhouse">
                    Invernadero
                </label>

                <select
                    id="automatic-greenhouse"
                    required
                >
                    <option value="">
                        Selecciona un invernadero
                    </option>
                </select>

            </div>


            <div class="form-group">

                <label for="automatic-zone">
                    Zona
                </label>

                <select
                    id="automatic-zone"
                    required
                    disabled
                >
                    <option value="">
                        Selecciona primero un invernadero
                    </option>
                </select>

            </div>


            <div class="form-group">

                <label>
                    Estado automático
                </label>

                <div class="automatic-toggle-row">

                    <label class="automatic-switch">

                        <input
                            id="automatic-enabled"
                            type="checkbox"
                        >

                        <span class="automatic-slider"></span>

                    </label>

                    <span id="automatic-enabled-label">
                        Desactivado
                    </span>

                </div>

            </div>


            <div class="form-group">

                <label for="automatic-duration">
                    Duración del riego
                </label>

                <div class="input-with-unit">

                    <input
                        id="automatic-duration"
                        type="number"
                        min="1"
                        max="1440"
                        value="10"
                        required
                    >

                    <span>
                        min
                    </span>

                </div>

            </div>


            <div class="form-group">

                <label for="automatic-water">
                    Agua estimada
                </label>

                <div class="input-with-unit">

                    <input
                        id="automatic-water"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Ej. 8"
                    >

                    <span>
                        L
                    </span>

                </div>

            </div>


            <div class="form-group">

                <label for="automatic-cooldown">
                    Tiempo mínimo entre riegos
                </label>

                <div class="input-with-unit">

                    <input
                        id="automatic-cooldown"
                        type="number"
                        min="1"
                        max="10080"
                        value="60"
                        required
                    >

                    <span>
                        min
                    </span>

                </div>

            </div>

        </div>


        <div class="automatic-threshold-box">

            <div>

                <span>
                    Humedad mínima configurada
                </span>

                <strong id="automatic-threshold">
                    —
                </strong>

            </div>


            <p>
                Cuando la humedad sea menor a este valor,
                el sistema evaluará si debe iniciar el riego.
            </p>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                id="save-automatic-button"
                class="primary-button"
                disabled
            >
                Guardar configuración
            </button>

        </div>

    </form>

</section>


<style>

    .automatic-description {
        margin-bottom: 18px;
        padding: 13px 15px;

        border: 1px solid #dfe9e2;
        border-radius: 10px;

        background: #f6faf7;
    }

    .automatic-description strong {
        display: block;

        color: #294336;

        font-size: 11px;
    }

    .automatic-description p {
        margin: 5px 0 0;

        color: #718078;

        font-size: 9px;

        line-height: 1.5;
    }

    .automatic-status-badge {
        padding: 6px 10px;

        border-radius: 20px;

        font-size: 9px;
        font-weight: 700;
    }

    .automatic-enabled {
        background: #e8f6ed;

        color: #176136;
    }

    .automatic-disabled {
        background: #ecefed;

        color: #6d756f;
    }

    .automatic-toggle-row {
        min-height: 40px;

        display: flex;
        align-items: center;

        gap: 10px;
    }

    .automatic-switch {
        position: relative;

        width: 43px;
        height: 23px;

        display: inline-block;
    }

    .automatic-switch input {
        width: 0;
        height: 0;

        opacity: 0;
    }

    .automatic-slider {
        position: absolute;

        inset: 0;

        border-radius: 25px;

        background: #cbd4ce;

        cursor: pointer;

        transition: .2s;
    }

    .automatic-slider::before {
        content: "";

        position: absolute;

        width: 17px;
        height: 17px;

        left: 3px;
        bottom: 3px;

        border-radius: 50%;

        background: #ffffff;

        transition: .2s;
    }

    .automatic-switch input:checked + .automatic-slider {
        background: #176136;
    }

    .automatic-switch input:checked + .automatic-slider::before {
        transform: translateX(20px);
    }

    .input-with-unit {
        display: flex;
        align-items: center;

        border: 1px solid #dce5de;
        border-radius: 8px;

        overflow: hidden;

        background: #ffffff;
    }

    .input-with-unit input {
        flex: 1;

        border: 0 !important;

        box-shadow: none !important;

        border-radius: 0 !important;
    }

    .input-with-unit span {
        padding: 0 11px;

        color: #77827b;

        font-size: 9px;
        font-weight: 700;
    }

    .automatic-threshold-box {
        margin-top: 17px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 20px;

        padding: 14px;

        border: 1px solid #dce7df;
        border-radius: 10px;

        background: #f4f8f5;
    }

    .automatic-threshold-box span {
        display: block;

        color: #728078;

        font-size: 9px;
    }

    .automatic-threshold-box strong {
        display: block;

        margin-top: 4px;

        color: #176136;

        font-size: 19px;
    }

    .automatic-threshold-box p {
        max-width: 420px;

        margin: 0;

        color: #79847d;

        font-size: 9px;

        line-height: 1.5;
    }

    @media (max-width: 700px) {

        .automatic-threshold-box {
            align-items: flex-start;

            flex-direction: column;
        }
    }

</style>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const automaticToken =
            sessionStorage.getItem(
                'lumatek_access_token'
            );


        if (!automaticToken) {

            window.location.href =
                '/login';

            return;
        }


        const greenhouse =
            document.getElementById(
                'automatic-greenhouse'
            );


        const zone =
            document.getElementById(
                'automatic-zone'
            );


        const enabled =
            document.getElementById(
                'automatic-enabled'
            );


        const enabledLabel =
            document.getElementById(
                'automatic-enabled-label'
            );


        const statusBadge =
            document.getElementById(
                'automatic-status-badge'
            );


        const duration =
            document.getElementById(
                'automatic-duration'
            );


        const water =
            document.getElementById(
                'automatic-water'
            );


        const cooldown =
            document.getElementById(
                'automatic-cooldown'
            );


        const threshold =
            document.getElementById(
                'automatic-threshold'
            );


        const saveButton =
            document.getElementById(
                'save-automatic-button'
            );


        const form =
            document.getElementById(
                'automatic-irrigation-form'
            );


        function handleAutomaticUnauthorized(
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


        function automaticMessage(
            message,
            type = 'success'
        ) {

            const box =
                document.getElementById(
                    'irrigation-message'
                );


            if (!box) {

                alert(message);

                return;
            }


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


        function updateVisualState() {

            if (enabled.checked) {

                enabledLabel.textContent =
                    'Activado';


                statusBadge.textContent =
                    'Activado';


                statusBadge.className =
                    'automatic-status-badge automatic-enabled';

            } else {

                enabledLabel.textContent =
                    'Desactivado';


                statusBadge.textContent =
                    'Desactivado';


                statusBadge.className =
                    'automatic-status-badge automatic-disabled';
            }
        }


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
                                    `Bearer ${automaticToken}`,
                            },
                        }
                    );


                if (
                    handleAutomaticUnauthorized(
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
                        : [];


                greenhouse.innerHTML =
                    `
                        <option value="">
                            Selecciona un invernadero
                        </option>
                    `;


                greenhouses.forEach(
                    item => {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            item.id;


                        option.textContent =
                            item.name;


                        greenhouse.appendChild(
                            option
                        );
                    }
                );


            } catch (error) {

                automaticMessage(
                    'No fue posible cargar los invernaderos.',
                    'error'
                );
            }
        }


        async function loadZones(
            greenhouseId
        ) {

            zone.disabled =
                true;


            saveButton.disabled =
                true;


            zone.innerHTML =
                `
                    <option value="">
                        Cargando zonas...
                    </option>
                `;


            if (!greenhouseId) {

                zone.innerHTML =
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
                                    `Bearer ${automaticToken}`,
                            },
                        }
                    );


                if (
                    handleAutomaticUnauthorized(
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


                zone.innerHTML =
                    `
                        <option value="">
                            Selecciona una zona
                        </option>
                    `;


                zones.forEach(
                    item => {

                        if (
                            item.status
                            &&
                            item.status !== 'active'
                        ) {

                            return;
                        }


                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            item.id;


                        option.textContent =
                            item.name;


                        zone.appendChild(
                            option
                        );
                    }
                );


                zone.disabled =
                    false;


            } catch (error) {

                zone.innerHTML =
                    `
                        <option value="">
                            Error al cargar zonas
                        </option>
                    `;


                automaticMessage(
                    'No fue posible cargar las zonas.',
                    'error'
                );
            }
        }


        async function loadSetting(
            zoneId
        ) {

            saveButton.disabled =
                true;


            if (!zoneId) {

                enabled.checked =
                    false;


                duration.value =
                    10;


                water.value =
                    '';


                cooldown.value =
                    60;


                threshold.textContent =
                    '—';


                updateVisualState();


                return;
            }


            try {

                const response =
                    await fetch(
                        `/api/zones/${zoneId}/irrigation-setting`,
                        {
                            headers: {

                                'Accept':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${automaticToken}`,
                            },
                        }
                    );


                if (
                    handleAutomaticUnauthorized(
                        response
                    )
                ) {
                    return;
                }


                const result =
                    await response.json();


                if (!response.ok) {

                    automaticMessage(
                        result.message
                        ?? 'No fue posible cargar la configuración.',
                        'error'
                    );


                    return;
                }


                const setting =
                    result.data;


                enabled.checked =
                    Boolean(
                        setting.automatic_enabled
                    );


                duration.value =
                    setting.duration_minutes
                    ?? 10;


                water.value =
                    setting.water_liters
                    ?? '';


                cooldown.value =
                    setting.cooldown_minutes
                    ?? 60;


                const minimum =
                    setting
                        .soil_humidity_threshold
                        ?.min_value;


                threshold.textContent =
                    minimum !== null
                    &&
                    minimum !== undefined
                        ? `${Number(minimum).toFixed(1)} %`
                        : 'Sin configurar';


                updateVisualState();


                saveButton.disabled =
                    false;


            } catch (error) {

                automaticMessage(
                    'No fue posible cargar la configuración.',
                    'error'
                );
            }
        }


        async function saveSetting(
            event
        ) {

            event.preventDefault();


            const zoneId =
                zone.value;


            if (!zoneId) {

                automaticMessage(
                    'Selecciona una zona.',
                    'error'
                );


                return;
            }


            const body = {

                automatic_enabled:
                    enabled.checked,

                duration_minutes:
                    Number(
                        duration.value
                    ),

                water_liters:
                    water.value !== ''
                        ? Number(
                            water.value
                        )
                        : null,

                cooldown_minutes:
                    Number(
                        cooldown.value
                    ),
            };


            saveButton.disabled =
                true;


            saveButton.textContent =
                'Guardando...';


            try {

                const response =
                    await fetch(
                        `/api/zones/${zoneId}/irrigation-setting`,
                        {
                            method:
                                'PUT',

                            headers: {

                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${automaticToken}`,
                            },

                            body:
                                JSON.stringify(
                                    body
                                ),
                        }
                    );


                if (
                    handleAutomaticUnauthorized(
                        response
                    )
                ) {
                    return;
                }


                const result =
                    await response.json();


                if (!response.ok) {

                    automaticMessage(
                        result.message
                        ?? 'No fue posible guardar la configuración.',
                        'error'
                    );


                    return;
                }


                automaticMessage(
                    result.message
                    ?? 'Configuración guardada correctamente.'
                );


                await loadSetting(
                    zoneId
                );


            } catch (error) {

                automaticMessage(
                    'No fue posible conectar con el servidor.',
                    'error'
                );


            } finally {

                saveButton.disabled =
                    false;


                saveButton.textContent =
                    'Guardar configuración';
            }
        }


        greenhouse.addEventListener(
            'change',
            function () {

                loadZones(
                    this.value
                );
            }
        );


        zone.addEventListener(
            'change',
            function () {

                loadSetting(
                    this.value
                );
            }
        );


        enabled.addEventListener(
            'change',
            updateVisualState
        );


        form.addEventListener(
            'submit',
            saveSetting
        );


        updateVisualState();

        loadGreenhouses();

    }
);

</script>