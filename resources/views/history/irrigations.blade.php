<section class="history-card irrigation-history-card">

    <div class="section-header">

        <div>
            <h2>Historial de riegos</h2>

            <p>
                Consulta los riegos manuales y automáticos realizados por zona.
            </p>
        </div>

    </div>


    <form id="irrigation-history-form">

        <div class="irrigation-history-filters">

            <div class="form-group">

                <label for="irrigation-history-greenhouse">
                    Invernadero
                </label>

                <select id="irrigation-history-greenhouse">

                    <option value="">
                        Todos los invernaderos
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="irrigation-history-zone">
                    Zona
                </label>

                <select
                    id="irrigation-history-zone"
                    disabled
                >

                    <option value="">
                        Todas las zonas
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="irrigation-history-mode">
                    Tipo de riego
                </label>

                <select id="irrigation-history-mode">

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


            <div class="form-group">

                <label for="irrigation-history-status">
                    Estado
                </label>

                <select id="irrigation-history-status">

                    <option value="">
                        Todos
                    </option>

                    <option value="started">
                        En curso
                    </option>

                    <option value="completed">
                        Completado
                    </option>

                    <option value="cancelled">
                        Cancelado
                    </option>

                    <option value="failed">
                        Fallido
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="irrigation-history-start">
                    Fecha inicial
                </label>

                <input
                    id="irrigation-history-start"
                    type="date"
                >

            </div>


            <div class="form-group">

                <label for="irrigation-history-end">
                    Fecha final
                </label>

                <input
                    id="irrigation-history-end"
                    type="date"
                >

            </div>

        </div>


        <div class="irrigation-history-actions">

            <button
                type="submit"
                id="irrigation-history-search"
                class="primary-button"
            >
                Consultar riegos
            </button>

        </div>

    </form>


    <div
        id="irrigation-history-message"
        class="message-box"
        style="display:none;"
    ></div>


    <div class="irrigation-history-summary">

        <article class="irrigation-summary-item">

            <span>
                Total
            </span>

            <strong id="irrigation-summary-total">
                0
            </strong>

        </article>


        <article class="irrigation-summary-item">

            <span>
                Manuales
            </span>

            <strong id="irrigation-summary-manual">
                0
            </strong>

        </article>


        <article class="irrigation-summary-item">

            <span>
                Automáticos
            </span>

            <strong id="irrigation-summary-automatic">
                0
            </strong>

        </article>


        <article class="irrigation-summary-item">

            <span>
                Completados
            </span>

            <strong id="irrigation-summary-completed">
                0
            </strong>

        </article>


        <article class="irrigation-summary-item">

            <span>
                Agua registrada
            </span>

            <strong id="irrigation-summary-water">
                0 L
            </strong>

        </article>

    </div>


    <div
        id="irrigation-history-loading"
        class="loading-state"
    >
        Cargando historial de riegos...
    </div>


    <div
        id="irrigation-history-empty"
        class="empty-state"
        style="display:none;"
    >

        <strong>
            No se encontraron riegos
        </strong>

        <span>
            No existen eventos de riego para los filtros seleccionados.
        </span>

    </div>


    <div
        id="irrigation-history-table-wrapper"
        class="irrigation-history-table-wrapper"
        style="display:none;"
    >

        <table class="irrigation-history-table">

            <thead>

                <tr>

                    <th>
                        Fecha
                    </th>

                    <th>
                        Invernadero
                    </th>

                    <th>
                        Zona
                    </th>

                    <th>
                        Tipo
                    </th>

                    <th>
                        Estado
                    </th>

                    <th>
                        Agua
                    </th>

                    <th>
                        Humedad
                    </th>

                    <th>
                        Duración
                    </th>

                </tr>

            </thead>


            <tbody id="irrigation-history-body"></tbody>

        </table>

    </div>

</section>


<style>

    .irrigation-history-card {
        margin-top: 20px;
    }


    .irrigation-history-filters {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 14px;
    }


    .irrigation-history-actions {
        display: flex;

        justify-content: flex-end;

        margin-top: 16px;
    }


    .irrigation-history-summary {
        display: grid;

        grid-template-columns:
            repeat(5, minmax(0, 1fr));

        gap: 12px;

        margin-top: 20px;
        margin-bottom: 18px;
    }


    .irrigation-summary-item {
        padding: 14px;

        border: 1px solid #e0e8e2;
        border-radius: 10px;

        background: #f8faf8;
    }


    .irrigation-summary-item span {
        display: block;

        color: #748078;

        font-size: 9px;
    }


    .irrigation-summary-item strong {
        display: block;

        margin-top: 6px;

        color: #173d27;

        font-size: 18px;
    }


    .irrigation-history-table-wrapper {
        width: 100%;

        overflow-x: auto;
    }


    .irrigation-history-table {
        width: 100%;

        border-collapse: collapse;
    }


    .irrigation-history-table th {
        padding: 11px 12px;

        border-bottom: 1px solid #dce5de;

        color: #617069;

        text-align: left;

        font-size: 9px;
        font-weight: 700;

        white-space: nowrap;
    }


    .irrigation-history-table td {
        padding: 12px;

        border-bottom: 1px solid #eef2ef;

        color: #394940;

        font-size: 10px;

        vertical-align: middle;
    }


    .irrigation-history-table tbody tr:hover {
        background: #f8faf8;
    }


    .irrigation-status {
        display: inline-flex;

        padding: 5px 8px;

        border-radius: 20px;

        font-size: 8px;
        font-weight: 700;
    }


    .irrigation-status-started {
        background: #e8f6ed;

        color: #176136;
    }


    .irrigation-status-completed {
        background: #eaf4ff;

        color: #2d6591;
    }


    .irrigation-status-cancelled,
    .irrigation-status-failed {
        background: #fff0f0;

        color: #a22e2e;
    }


    .irrigation-mode {
        display: inline-flex;

        padding: 5px 8px;

        border-radius: 20px;

        background: #eef3ef;

        color: #526159;

        font-size: 8px;
        font-weight: 700;
    }


    @media (max-width: 1100px) {

        .irrigation-history-summary {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }


        .irrigation-history-filters {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }


    @media (max-width: 650px) {

        .irrigation-history-summary,
        .irrigation-history-filters {
            grid-template-columns: 1fr;
        }

    }

</style>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const irrigationHistoryToken =
            sessionStorage.getItem(
                'lumatek_access_token'
            );


        if (!irrigationHistoryToken) {

            window.location.href =
                '/login';

            return;
        }


        const greenhouse =
            document.getElementById(
                'irrigation-history-greenhouse'
            );


        const zone =
            document.getElementById(
                'irrigation-history-zone'
            );


        const mode =
            document.getElementById(
                'irrigation-history-mode'
            );


        const status =
            document.getElementById(
                'irrigation-history-status'
            );


        const startDate =
            document.getElementById(
                'irrigation-history-start'
            );


        const endDate =
            document.getElementById(
                'irrigation-history-end'
            );


        const form =
            document.getElementById(
                'irrigation-history-form'
            );


        const button =
            document.getElementById(
                'irrigation-history-search'
            );


        const loading =
            document.getElementById(
                'irrigation-history-loading'
            );


        const empty =
            document.getElementById(
                'irrigation-history-empty'
            );


        const tableWrapper =
            document.getElementById(
                'irrigation-history-table-wrapper'
            );


        const body =
            document.getElementById(
                'irrigation-history-body'
            );


        function initializeIrrigationDates() {

            const today =
                new Date();


            const sevenDaysAgo =
                new Date();


            sevenDaysAgo.setDate(
                today.getDate() - 7
            );


            startDate.value =
                irrigationInputDate(
                    sevenDaysAgo
                );


            endDate.value =
                irrigationInputDate(
                    today
                );
        }


        function irrigationInputDate(
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


        function irrigationUnauthorized(
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


        function showIrrigationMessage(
            text
        ) {

            const box =
                document.getElementById(
                    'irrigation-history-message'
                );


            box.textContent =
                text;


            box.className =
                'message-box message-error';


            box.style.display =
                'block';
        }


        function hideIrrigationMessage() {

            document.getElementById(
                'irrigation-history-message'
            ).style.display =
                'none';
        }


        async function loadIrrigationGreenhouses() {

            try {

                const response =
                    await fetch(
                        '/api/greenhouses',
                        {
                            headers: {

                                'Accept':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${irrigationHistoryToken}`,
                            },
                        }
                    );


                if (
                    irrigationUnauthorized(
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
                            Todos los invernaderos
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

                showIrrigationMessage(
                    'No fue posible cargar los invernaderos.'
                );
            }
        }


        async function loadIrrigationZones(
            greenhouseId
        ) {

            zone.innerHTML =
                `
                    <option value="">
                        Todas las zonas
                    </option>
                `;


            if (!greenhouseId) {

                zone.disabled =
                    true;


                return;
            }


            zone.disabled =
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
                                    `Bearer ${irrigationHistoryToken}`,
                            },
                        }
                    );


                if (
                    irrigationUnauthorized(
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


                zones.forEach(
                    item => {

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

                showIrrigationMessage(
                    'No fue posible cargar las zonas.'
                );
            }
        }


        async function loadIrrigationHistory() {

            hideIrrigationMessage();


            loading.style.display =
                'block';


            empty.style.display =
                'none';


            tableWrapper.style.display =
                'none';


            button.disabled =
                true;


            button.textContent =
                'Consultando...';


            const params =
                new URLSearchParams();


            if (greenhouse.value) {

                params.append(
                    'greenhouse_id',
                    greenhouse.value
                );
            }


            if (zone.value) {

                params.append(
                    'zone_id',
                    zone.value
                );
            }


            if (mode.value) {

                params.append(
                    'mode',
                    mode.value
                );
            }


            if (status.value) {

                params.append(
                    'status',
                    status.value
                );
            }


            if (startDate.value) {

                params.append(
                    'start_date',
                    startDate.value
                );
            }


            if (endDate.value) {

                params.append(
                    'end_date',
                    endDate.value
                );
            }


            try {

                const response =
                    await fetch(
                        `/api/history/irrigations?${params.toString()}`,
                        {
                            headers: {

                                'Accept':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${irrigationHistoryToken}`,
                            },
                        }
                    );


                if (
                    irrigationUnauthorized(
                        response
                    )
                ) {
                    return;
                }


                const result =
                    await response.json();


                if (!response.ok) {

                    showIrrigationMessage(
                        result.message
                        ?? 'No fue posible consultar los riegos.'
                    );


                    return;
                }


                renderIrrigationSummary(
                    result.summary
                );


                renderIrrigationTable(
                    result.data ?? []
                );


            } catch (error) {

                showIrrigationMessage(
                    'No fue posible conectar con el servidor.'
                );


            } finally {

                loading.style.display =
                    'none';


                button.disabled =
                    false;


                button.textContent =
                    'Consultar riegos';
            }
        }


        function renderIrrigationSummary(
            summary
        ) {

            document.getElementById(
                'irrigation-summary-total'
            ).textContent =
                summary?.total
                ?? 0;


            document.getElementById(
                'irrigation-summary-manual'
            ).textContent =
                summary?.manual
                ?? 0;


            document.getElementById(
                'irrigation-summary-automatic'
            ).textContent =
                summary?.automatic
                ?? 0;


            document.getElementById(
                'irrigation-summary-completed'
            ).textContent =
                summary?.completed
                ?? 0;


            document.getElementById(
                'irrigation-summary-water'
            ).textContent =
                `${Number(
                    summary?.total_water_liters
                    ?? 0
                ).toFixed(2)} L`;
        }


        function renderIrrigationTable(
            events
        ) {

            body.innerHTML =
                '';


            if (!events.length) {

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


            events.forEach(
                event => {

                    const row =
                        document.createElement(
                            'tr'
                        );


                    row.innerHTML = `

                        <td>
                            ${escapeIrrigationHtml(
                                irrigationDateTime(
                                    event.started_at
                                )
                            )}
                        </td>


                        <td>
                            ${escapeIrrigationHtml(
                                event.greenhouse?.name
                                ?? '—'
                            )}
                        </td>


                        <td>
                            ${escapeIrrigationHtml(
                                event.zone?.name
                                ?? '—'
                            )}
                        </td>


                        <td>

                            <span class="irrigation-mode">

                                ${escapeIrrigationHtml(
                                    event.mode_label
                                )}

                            </span>

                        </td>


                        <td>

                            <span
                                class="
                                    irrigation-status
                                    irrigation-status-${escapeIrrigationHtml(
                                        event.status
                                    )}
                                "
                            >

                                ${escapeIrrigationHtml(
                                    event.status_label
                                )}

                            </span>

                        </td>


                        <td>

                            ${
                                event.water_liters !== null
                                    ? `${Number(
                                        event.water_liters
                                    ).toFixed(2)} L`
                                    : '—'
                            }

                        </td>


                        <td>

                            ${irrigationHumidity(
                                event.soil_humidity_before
                            )}

                            →

                            ${irrigationHumidity(
                                event.soil_humidity_after
                            )}

                        </td>


                        <td>

                            ${
                                event.duration_minutes !== null
                                    ? `${event.duration_minutes} min`
                                    : '—'
                            }

                        </td>
                    `;


                    body.appendChild(
                        row
                    );
                }
            );
        }


        function irrigationHumidity(
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


        function irrigationDateTime(
            value
        ) {

            if (!value) {

                return '—';
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
                        'short',
                }
            );
        }


        function escapeIrrigationHtml(
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


        greenhouse.addEventListener(
            'change',
            function () {

                loadIrrigationZones(
                    this.value
                );
            }
        );


        form.addEventListener(
            'submit',
            function (
                event
            ) {

                event.preventDefault();


                loadIrrigationHistory();
            }
        );


        initializeIrrigationDates();

        loadIrrigationGreenhouses();

        loadIrrigationHistory();

    }
);

</script>