@extends('layouts.app')

@section('title', 'Configuración | LumaTek')

@section('page-title', 'Configuración de empresa')

@section(
    'page-subtitle',
    'Administra la información general de tu empresa'
)

@section('content')

<div class="settings-page">

    {{-- =========================================================
         MENSAJE
    ========================================================== --}}

    <div
        id="settings-message"
        class="settings-message"
        style="display: none;"
    ></div>


    {{-- =========================================================
         INFORMACIÓN GENERAL
    ========================================================== --}}

    <section class="settings-card">

        <div class="settings-card-header">

            <div>

                <h2>
                    Información de la empresa
                </h2>

                <p>
                    Estos datos identifican a tu empresa dentro
                    de la plataforma LumaTek.
                </p>

            </div>


            <span
                id="company-status"
                class="status-badge"
            >
                Cargando...
            </span>

        </div>


        <div class="settings-card-body">

            <div
                id="settings-loading"
                class="settings-state"
            >
                Cargando información de la empresa...
            </div>


            <form
                id="company-form"
                style="display: none;"
            >

                <div class="settings-grid">

                    {{-- NOMBRE COMERCIAL --}}

                    <div class="form-group">

                        <label for="company-name">
                            Nombre de la empresa
                        </label>

                        <input
                            type="text"
                            id="company-name"
                            maxlength="150"
                            placeholder="Ej. LumaTek Agro"
                            required
                        >

                        <small>
                            Nombre comercial visible dentro del sistema.
                        </small>

                    </div>


                    {{-- RAZÓN SOCIAL --}}

                    <div class="form-group">

                        <label for="company-legal-name">
                            Razón social
                        </label>

                        <input
                            type="text"
                            id="company-legal-name"
                            maxlength="200"
                            placeholder="Ej. LumaTek Agro S.A. de C.V."
                        >

                        <small>
                            Campo opcional para la denominación legal.
                        </small>

                    </div>


                    {{-- CORREO --}}

                    <div class="form-group">

                        <label for="company-email">
                            Correo de contacto
                        </label>

                        <input
                            type="email"
                            id="company-email"
                            maxlength="150"
                            placeholder="contacto@empresa.com"
                            required
                        >

                        <small>
                            Correo general de contacto de la empresa.
                        </small>

                    </div>


                    {{-- TELÉFONO --}}

                    <div class="form-group">

                        <label for="company-phone">
                            Teléfono
                        </label>

                        <input
                            type="text"
                            id="company-phone"
                            maxlength="20"
                            placeholder="Ej. 919 123 4567"
                        >

                        <small>
                            Número de contacto de la empresa.
                        </small>

                    </div>

                </div>


                {{-- =================================================
                     INFORMACIÓN NO EDITABLE
                ================================================== --}}

                <div class="settings-info-section">

                    <h3>
                        Información del sistema
                    </h3>


                    <div class="system-info-grid">

                        <div class="system-info-item">

                            <span>
                                Identificador
                            </span>

                            <strong id="company-id">
                                -
                            </strong>

                        </div>


                        <div class="system-info-item">

                            <span>
                                Estado
                            </span>

                            <strong id="company-status-text">
                                -
                            </strong>

                        </div>


                        <div class="system-info-item">

                            <span>
                                Fecha de registro
                            </span>

                            <strong id="company-created-at">
                                -
                            </strong>

                        </div>

                    </div>


                    <p class="system-note">
                        El estado de la empresa no puede modificarse
                        desde esta sección.
                    </p>

                </div>


                {{-- =================================================
                     ACCIONES
                ================================================== --}}

                <div class="settings-actions">

                    <button
                        type="submit"
                        id="save-settings-button"
                        class="btn-primary"
                    >
                        Guardar cambios
                    </button>

                </div>

            </form>

        </div>

    </section>

</div>

@endsection


@push('styles')

<style>

    .settings-page {
        width: 100%;
        max-width: 1050px;
        margin: 0 auto;
    }


    /* =========================================================
       MENSAJE
    ========================================================== */

    .settings-message {
        margin-bottom: 20px;

        padding: 12px 15px;

        border-radius: 9px;

        font-size: 11px;
        line-height: 1.5;
    }

    .settings-message.success {
        background: #eaf7ee;
        border: 1px solid #badcc5;
        color: #176136;
    }

    .settings-message.error {
        background: #fff0f0;
        border: 1px solid #e7c3c3;
        color: #9b3030;
    }


    /* =========================================================
       TARJETA
    ========================================================== */

    .settings-card {
        overflow: hidden;

        background: #ffffff;

        border: 1px solid #e1e9e3;
        border-radius: 13px;
    }

    .settings-card-header {
        min-height: 82px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 20px;

        padding: 19px 21px;

        border-bottom: 1px solid #edf1ee;
    }

    .settings-card-header h2 {
        margin: 0;

        color: #173d27;

        font-size: 16px;
    }

    .settings-card-header p {
        margin: 5px 0 0;

        color: #7c8780;

        font-size: 10px;
        line-height: 1.5;
    }

    .settings-card-body {
        padding: 22px;
    }


    /* =========================================================
       ESTADO
    ========================================================== */

    .status-badge {
        display: inline-flex;
        align-items: center;

        padding: 6px 10px;

        border-radius: 20px;

        background: #f1f4f2;
        color: #647068;

        font-size: 9px;
        font-weight: 700;

        white-space: nowrap;
    }

    .status-badge.active {
        background: #eaf7ee;
        color: #176136;
    }

    .status-badge.inactive {
        background: #f7eeee;
        color: #944848;
    }


    /* =========================================================
       FORMULARIO
    ========================================================== */

    .settings-grid {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 18px 20px;
    }

    .form-group label {
        display: block;

        margin-bottom: 6px;

        color: #394c40;

        font-size: 10px;
        font-weight: 700;
    }

    .form-group input {
        width: 100%;
        min-height: 43px;

        padding: 0 12px;

        border: 1px solid #d8e2db;
        border-radius: 8px;

        background: #ffffff;
        color: #293d30;

        font-size: 11px;

        outline: none;

        transition:
            border-color .15s ease,
            box-shadow .15s ease;
    }

    .form-group input:focus {
        border-color: #71a485;

        box-shadow:
            0 0 0 3px rgba(65, 132, 87, .08);
    }

    .form-group small {
        display: block;

        margin-top: 5px;

        color: #89938c;

        font-size: 9px;
        line-height: 1.4;
    }


    /* =========================================================
       INFORMACIÓN DEL SISTEMA
    ========================================================== */

    .settings-info-section {
        margin-top: 28px;

        padding-top: 22px;

        border-top: 1px solid #edf1ee;
    }

    .settings-info-section h3 {
        margin: 0 0 14px;

        color: #34483b;

        font-size: 12px;
    }

    .system-info-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 13px;
    }

    .system-info-item {
        padding: 13px;

        border-radius: 9px;

        background: #f6f9f7;
    }

    .system-info-item span {
        display: block;

        color: #808b83;

        font-size: 9px;
    }

    .system-info-item strong {
        display: block;

        margin-top: 5px;

        color: #33473a;

        font-size: 11px;

        overflow-wrap: anywhere;
    }

    .system-note {
        margin: 11px 0 0;

        color: #858f88;

        font-size: 9px;
        line-height: 1.5;
    }


    /* =========================================================
       ACCIONES
    ========================================================== */

    .settings-actions {
        display: flex;

        margin-top: 23px;
    }

    .btn-primary {
        padding: 10px 15px;

        border: 1px solid #176136;
        border-radius: 8px;

        background: #176136;
        color: #ffffff;

        cursor: pointer;

        font-size: 10px;
        font-weight: 700;

        transition: background .15s ease;
    }

    .btn-primary:hover {
        background: #124d2b;
    }

    .btn-primary:disabled {
        opacity: .65;
        cursor: not-allowed;
    }


    /* =========================================================
       CARGANDO
    ========================================================== */

    .settings-state {
        padding: 42px 20px;

        color: #7b867f;

        text-align: center;

        font-size: 11px;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 750px) {

        .settings-grid,
        .system-info-grid {
            grid-template-columns: 1fr;
        }

        .settings-card-header {
            align-items: flex-start;
            flex-direction: column;
        }

    }

</style>

@endpush


@push('scripts')

<script>

    const settingsToken =
        sessionStorage.getItem(
            'lumatek_access_token'
        );


    const storedSettingsUser =
        sessionStorage.getItem(
            'lumatek_user'
        );


    /*
    |--------------------------------------------------------------------------
    | SESIÓN
    |--------------------------------------------------------------------------
    */

    if (
        !settingsToken
        || !storedSettingsUser
    ) {

        window.location.href =
            '/login';
    }


    let settingsUser = null;


    try {

        settingsUser =
            JSON.parse(
                storedSettingsUser
            );

    } catch (error) {

        sessionStorage.clear();

        window.location.href =
            '/login';
    }


    /*
    |--------------------------------------------------------------------------
    | CONTROL DE ROL
    |--------------------------------------------------------------------------
    */

    if (
        settingsUser
        && settingsUser.role !==
            'company_admin'
    ) {

        window.location.href =
            '/dashboard';
    }


    /*
    |--------------------------------------------------------------------------
    | ELEMENTOS
    |--------------------------------------------------------------------------
    */

    const companyForm =
        document.getElementById(
            'company-form'
        );


    const companyName =
        document.getElementById(
            'company-name'
        );


    const companyLegalName =
        document.getElementById(
            'company-legal-name'
        );


    const companyEmail =
        document.getElementById(
            'company-email'
        );


    const companyPhone =
        document.getElementById(
            'company-phone'
        );


    const companyId =
        document.getElementById(
            'company-id'
        );


    const companyStatus =
        document.getElementById(
            'company-status'
        );


    const companyStatusText =
        document.getElementById(
            'company-status-text'
        );


    const companyCreatedAt =
        document.getElementById(
            'company-created-at'
        );


    const settingsLoading =
        document.getElementById(
            'settings-loading'
        );


    const settingsMessage =
        document.getElementById(
            'settings-message'
        );


    const saveSettingsButton =
        document.getElementById(
            'save-settings-button'
        );


    /*
    |--------------------------------------------------------------------------
    | UTILIDADES
    |--------------------------------------------------------------------------
    */

    function logoutIfUnauthorized(
        response
    ) {

        if (response.status !== 401) {
            return false;
        }


        sessionStorage.clear();


        window.location.href =
            '/login';


        return true;
    }


    function showSettingsMessage(
        text,
        type = 'success'
    ) {

        settingsMessage.textContent =
            text;


        settingsMessage.className =
            `settings-message ${type}`;


        settingsMessage.style.display =
            'block';
    }


    function hideSettingsMessage() {

        settingsMessage.style.display =
            'none';
    }


    function getSettingsErrors(
        result
    ) {

        if (result.errors) {

            return Object
                .values(result.errors)
                .flat()
                .join(' ');
        }


        return result.message
            ?? 'Ocurrió un error inesperado.';
    }


    function formatCompanyDate(
        value
    ) {

        if (!value) {
            return 'Sin información';
        }


        const date =
            new Date(
                value.replace(
                    ' ',
                    'T'
                )
            );


        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return value;
        }


        return date.toLocaleString(
            'es-MX',
            {
                dateStyle:
                    'medium',

                timeStyle:
                    'short'
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    async function loadCompanySettings() {

        hideSettingsMessage();


        settingsLoading.style.display =
            'block';


        companyForm.style.display =
            'none';


        try {

            const response =
                await fetch(
                    '/api/company/settings',
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${settingsToken}`
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

                settingsLoading.style.display =
                    'none';


                showSettingsMessage(
                    getSettingsErrors(result),
                    'error'
                );

                return;
            }


            const company =
                result.data;


            companyName.value =
                company.name ?? '';


            companyLegalName.value =
                company.legal_name ?? '';


            companyEmail.value =
                company.email ?? '';


            companyPhone.value =
                company.phone ?? '';


            companyId.textContent =
                company.id ?? '-';


            const statusLabel =
                company.status === 'active'
                    ? 'Activa'
                    : 'Inactiva';


            companyStatus.textContent =
                statusLabel;


            companyStatus.className =
                `status-badge ${
                    company.status === 'active'
                        ? 'active'
                        : 'inactive'
                }`;


            companyStatusText.textContent =
                statusLabel;


            companyCreatedAt.textContent =
                formatCompanyDate(
                    company.created_at
                );


            settingsLoading.style.display =
                'none';


            companyForm.style.display =
                'block';


        } catch (error) {

            settingsLoading.style.display =
                'none';


            showSettingsMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GUARDAR CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */

    companyForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            hideSettingsMessage();


            const payload = {

                name:
                    companyName.value
                        .trim(),

                legal_name:
                    companyLegalName.value
                        .trim()
                    || null,

                email:
                    companyEmail.value
                        .trim()
                        .toLowerCase(),

                phone:
                    companyPhone.value
                        .trim()
                    || null
            };


            saveSettingsButton.disabled =
                true;


            saveSettingsButton.textContent =
                'Guardando...';


            try {

                const response =
                    await fetch(
                        '/api/company/settings',
                        {
                            method:
                                'PUT',

                            headers: {

                                'Accept':
                                    'application/json',

                                'Content-Type':
                                    'application/json',

                                'Authorization':
                                    `Bearer ${settingsToken}`
                            },

                            body:
                                JSON.stringify(
                                    payload
                                )
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

                    showSettingsMessage(
                        getSettingsErrors(
                            result
                        ),
                        'error'
                    );

                    return;
                }


                await loadCompanySettings();


showSettingsMessage(
    result.message
    ?? 'Configuración actualizada correctamente.'
);


            } catch (error) {

                showSettingsMessage(
                    'No fue posible conectar con el servidor.',
                    'error'
                );

            } finally {

                saveSettingsButton.disabled =
                    false;


                saveSettingsButton.textContent =
                    'Guardar cambios';
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    loadCompanySettings();

</script>

@endpush