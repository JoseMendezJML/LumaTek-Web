@extends('layouts.app')

@section('title', 'Usuarios | LumaTek')

@section('page-title', 'Usuarios y empleados')

@section(
    'page-subtitle',
    'Administra los usuarios que pertenecen a tu empresa'
)

@section('content')

<div class="users-page">

    {{-- =========================================================
         RESUMEN
    ========================================================== --}}

    <section class="users-summary-grid">

        <article class="summary-card">
            <span class="summary-label">
                Usuarios
            </span>

            <strong
                id="summary-total"
                class="summary-value"
            >
                0
            </strong>
        </article>


        <article class="summary-card summary-employees">
            <span class="summary-label">
                Empleados
            </span>

            <strong
                id="summary-employees"
                class="summary-value"
            >
                0
            </strong>
        </article>


        <article class="summary-card summary-active">
            <span class="summary-label">
                Empleados activos
            </span>

            <strong
                id="summary-active"
                class="summary-value"
            >
                0
            </strong>
        </article>


        <article class="summary-card summary-inactive">
            <span class="summary-label">
                Empleados inactivos
            </span>

            <strong
                id="summary-inactive"
                class="summary-value"
            >
                0
            </strong>
        </article>

    </section>


    <div
        id="page-message"
        class="users-message"
        style="display: none;"
    ></div>


    {{-- =========================================================
         CONTENIDO
    ========================================================== --}}

    <div class="users-layout">

        {{-- =====================================================
             FORMULARIO
        ====================================================== --}}

        <section class="users-card">

            <div class="users-card-header">

                <div>
                    <h2 id="form-title">
                        Registrar empleado
                    </h2>

                    <p id="form-description">
                        Crea una cuenta para un empleado de tu empresa.
                    </p>
                </div>

            </div>


            <div class="users-card-body">

                <form id="user-form">

                    <input
                        type="hidden"
                        id="user-id"
                    >


                    <div class="form-group">

                        <label for="user-name">
                            Nombre completo
                        </label>

                        <input
                            type="text"
                            id="user-name"
                            maxlength="120"
                            placeholder="Ej. María López"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="user-email">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            id="user-email"
                            maxlength="150"
                            placeholder="empleado@empresa.com"
                            required
                        >

                    </div>


                    <div
                        id="password-fields"
                        class="password-fields"
                    >

                        <div class="form-group">

                            <label for="user-password">
                                Contraseña
                            </label>

                            <input
                                type="password"
                                id="user-password"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="user-password-confirmation">
                                Confirmar contraseña
                            </label>

                            <input
                                type="password"
                                id="user-password-confirmation"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Repite la contraseña"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-note">

                        <strong>
                            Rol asignado:
                        </strong>

                        Empleado

                        <span>
                            La cuenta pertenecerá automáticamente
                            a tu empresa.
                        </span>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            id="save-user-button"
                            class="btn-primary"
                        >
                            Registrar empleado
                        </button>


                        <button
                            type="button"
                            id="cancel-edit-button"
                            class="btn-secondary"
                            style="display: none;"
                        >
                            Cancelar edición
                        </button>

                    </div>

                </form>

            </div>

        </section>


        {{-- =====================================================
             LISTADO
        ====================================================== --}}

        <section class="users-card users-list-card">

            <div class="users-card-header">

                <div>
                    <h2>
                        Usuarios registrados
                    </h2>

                    <p>
                        Usuarios asociados a tu empresa.
                    </p>
                </div>


                <span
                    id="users-count"
                    class="users-count"
                >
                    0 usuarios
                </span>

            </div>


            <div class="users-card-body">

                <div class="users-toolbar">

                    <div class="search-box">

                        <label for="user-search">
                            Buscar
                        </label>

                        <input
                            type="search"
                            id="user-search"
                            placeholder="Nombre o correo..."
                        >

                    </div>


                    <div class="filter-box">

                        <label for="status-filter">
                            Estado
                        </label>

                        <select id="status-filter">

                            <option value="">
                                Todos
                            </option>

                            <option value="active">
                                Activos
                            </option>

                            <option value="inactive">
                                Inactivos
                            </option>

                        </select>

                    </div>

                </div>


                <div
                    id="users-loading"
                    class="users-state"
                >
                    Cargando usuarios...
                </div>


                <div
                    id="users-empty"
                    class="users-state"
                    style="display: none;"
                >
                    No se encontraron usuarios.
                </div>


                <div
                    id="users-table-container"
                    class="users-table-container"
                    style="display: none;"
                >

                    <table class="users-table">

                        <thead>

                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Verificación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody id="users-table-body">
                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </div>

</div>

@endsection


@push('styles')

<style>

    .users-page {
        width: 100%;
    }


    /* =========================================================
       RESUMEN
    ========================================================== */

    .users-summary-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 24px;
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

    .summary-employees {
        border-left: 4px solid #376c4b;
    }

    .summary-active {
        border-left: 4px solid #2d7b4a;
    }

    .summary-inactive {
        border-left: 4px solid #9b5b45;
    }


    /* =========================================================
       MENSAJE
    ========================================================== */

    .users-message {
        margin-bottom: 20px;
        padding: 12px 15px;
        border-radius: 9px;
        font-size: 11px;
        line-height: 1.5;
    }

    .users-message.success {
        background: #eaf7ee;
        border: 1px solid #badcc5;
        color: #176136;
    }

    .users-message.error {
        background: #fff0f0;
        border: 1px solid #e7c3c3;
        color: #9b3030;
    }


    /* =========================================================
       DISTRIBUCIÓN
    ========================================================== */

    .users-layout {
        display: grid;
        grid-template-columns:
            minmax(280px, 360px)
            minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }


    /* =========================================================
       TARJETAS
    ========================================================== */

    .users-card {
        background: #ffffff;
        border: 1px solid #e1e9e3;
        border-radius: 13px;
        overflow: hidden;
    }

    .users-card-header {
        min-height: 74px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 15px;

        padding: 17px 19px;

        border-bottom: 1px solid #edf1ee;
    }

    .users-card-header h2 {
        margin: 0;
        color: #173d27;
        font-size: 15px;
    }

    .users-card-header p {
        margin: 5px 0 0;
        color: #7c8780;
        font-size: 10px;
        line-height: 1.5;
    }

    .users-card-body {
        padding: 19px;
    }

    .users-count {
        color: #6e7a72;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }


    /* =========================================================
       FORMULARIO
    ========================================================== */

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        color: #394c40;
        font-size: 10px;
        font-weight: 700;
    }

    .form-group input,
    .users-toolbar input,
    .users-toolbar select {
        width: 100%;
        min-height: 42px;

        padding: 0 11px;

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

    .form-group input:focus,
    .users-toolbar input:focus,
    .users-toolbar select:focus {
        border-color: #71a485;

        box-shadow:
            0 0 0 3px rgba(65, 132, 87, .08);
    }

    .form-note {
        margin-top: 4px;

        padding: 11px 12px;

        border-radius: 8px;

        background: #f5f8f6;

        color: #58675e;

        font-size: 10px;
        line-height: 1.5;
    }

    .form-note strong {
        color: #32463a;
    }

    .form-note span {
        display: block;
        margin-top: 3px;
    }

    .form-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }


    /* =========================================================
       BOTONES
    ========================================================== */

    .btn-primary,
    .btn-secondary,
    .table-action {
        border-radius: 8px;
        cursor: pointer;

        font-size: 10px;
        font-weight: 700;

        transition:
            background .15s ease,
            border-color .15s ease;
    }

    .btn-primary {
        padding: 10px 14px;

        border: 1px solid #176136;

        background: #176136;
        color: #ffffff;
    }

    .btn-primary:hover {
        background: #124d2b;
    }

    .btn-primary:disabled {
        opacity: .65;
        cursor: not-allowed;
    }

    .btn-secondary {
        padding: 10px 14px;

        border: 1px solid #d6e0d8;

        background: #ffffff;
        color: #44564a;
    }

    .btn-secondary:hover {
        background: #f5f8f6;
    }


    /* =========================================================
       FILTROS
    ========================================================== */

    .users-toolbar {
        display: grid;
        grid-template-columns:
            minmax(180px, 1fr)
            160px;

        gap: 12px;

        margin-bottom: 17px;
    }

    .users-toolbar label {
        display: block;
        margin-bottom: 5px;

        color: #59675e;

        font-size: 9px;
        font-weight: 700;
    }


    /* =========================================================
       TABLA
    ========================================================== */

    .users-table-container {
        width: 100%;
        overflow-x: auto;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    .users-table th {
        padding: 10px 9px;

        border-bottom: 1px solid #dfe7e1;

        color: #79847d;

        text-align: left;
        text-transform: uppercase;

        font-size: 8px;
        letter-spacing: .4px;
    }

    .users-table td {
        padding: 13px 9px;

        border-bottom: 1px solid #edf1ee;

        color: #435349;

        vertical-align: middle;

        font-size: 10px;
    }

    .users-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .users-table tbody tr:hover {
        background: #fafcfb;
    }


    /* =========================================================
       USUARIO
    ========================================================== */

    .user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 190px;
    }

    .user-initial {
        width: 34px;
        height: 34px;

        flex: 0 0 34px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50%;

        background: #e7f2ea;
        color: #176136;

        font-size: 11px;
        font-weight: 700;
    }

    .user-details strong {
        display: block;

        max-width: 190px;

        color: #304339;

        font-size: 10px;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-details span {
        display: block;

        max-width: 200px;

        margin-top: 3px;

        color: #879189;

        font-size: 9px;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .current-user-text {
        display: block;
        margin-top: 3px;
        color: #176136;
        font-size: 8px;
        font-weight: 700;
    }


    /* =========================================================
       BADGES
    ========================================================== */

    .badge {
        display: inline-flex;
        align-items: center;

        padding: 5px 8px;

        border-radius: 20px;

        font-size: 8px;
        font-weight: 700;

        white-space: nowrap;
    }

    .badge-admin {
        background: #eef4f0;
        color: #315c40;
    }

    .badge-employee {
        background: #eef4fb;
        color: #386491;
    }

    .badge-active {
        background: #eaf7ee;
        color: #176136;
    }

    .badge-inactive {
        background: #f5eeee;
        color: #914545;
    }

    .badge-verified {
        background: #edf7f0;
        color: #277144;
    }

    .badge-pending {
        background: #fff6e7;
        color: #946521;
    }


    /* =========================================================
       ACCIONES
    ========================================================== */

    .table-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .table-action {
        padding: 7px 9px;

        border: 1px solid #d8e2da;

        background: #ffffff;
        color: #405248;
    }

    .table-action:hover {
        background: #f4f8f5;
    }

    .table-action.status {
        border-color: #ddc8b8;
        color: #865638;
    }

    .table-action.activate {
        border-color: #bddbc6;
        color: #176136;
    }

    .table-current {
        color: #859087;
        font-size: 9px;
        font-weight: 600;
    }


    /* =========================================================
       ESTADOS
    ========================================================== */

    .users-state {
        padding: 38px 15px;

        color: #7a857e;

        text-align: center;

        font-size: 11px;
    }


    /* =========================================================
       RESPONSIVE
    ========================================================== */

    @media (max-width: 1100px) {

        .users-summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .users-layout {
            grid-template-columns: 1fr;
        }

    }


    @media (max-width: 650px) {

        .users-summary-grid {
            grid-template-columns: 1fr;
        }

        .users-toolbar {
            grid-template-columns: 1fr;
        }

        .users-card-header {
            align-items: flex-start;
            flex-direction: column;
        }

    }

</style>

@endpush


@push('scripts')

<script>

    const usersToken =
        sessionStorage.getItem(
            'lumatek_access_token'
        );


    const storedCurrentUser =
        sessionStorage.getItem(
            'lumatek_user'
        );


    /*
    |--------------------------------------------------------------------------
    | SESIÓN
    |--------------------------------------------------------------------------
    */

    if (
        !usersToken
        || !storedCurrentUser
    ) {
        window.location.href =
            '/login';
    }


    let currentUser = null;


    try {

        currentUser =
            JSON.parse(
                storedCurrentUser
            );

    } catch (error) {

        sessionStorage.clear();

        window.location.href =
            '/login';
    }


    /*
    |--------------------------------------------------------------------------
    | ACCESO
    |--------------------------------------------------------------------------
    */

    if (
        currentUser
        && currentUser.role !== 'company_admin'
    ) {

        window.location.href =
            '/dashboard';
    }


    /*
    |--------------------------------------------------------------------------
    | ELEMENTOS
    |--------------------------------------------------------------------------
    */

    const userForm =
        document.getElementById(
            'user-form'
        );


    const userId =
        document.getElementById(
            'user-id'
        );


    const userName =
        document.getElementById(
            'user-name'
        );


    const userEmail =
        document.getElementById(
            'user-email'
        );


    const userPassword =
        document.getElementById(
            'user-password'
        );


    const userPasswordConfirmation =
        document.getElementById(
            'user-password-confirmation'
        );


    const passwordFields =
        document.getElementById(
            'password-fields'
        );


    const formTitle =
        document.getElementById(
            'form-title'
        );


    const formDescription =
        document.getElementById(
            'form-description'
        );


    const saveUserButton =
        document.getElementById(
            'save-user-button'
        );


    const cancelEditButton =
        document.getElementById(
            'cancel-edit-button'
        );


    const pageMessage =
        document.getElementById(
            'page-message'
        );


    const usersLoading =
        document.getElementById(
            'users-loading'
        );


    const usersEmpty =
        document.getElementById(
            'users-empty'
        );


    const usersTableContainer =
        document.getElementById(
            'users-table-container'
        );


    const usersTableBody =
        document.getElementById(
            'users-table-body'
        );


    const usersCount =
        document.getElementById(
            'users-count'
        );


    const userSearch =
        document.getElementById(
            'user-search'
        );


    const statusFilter =
        document.getElementById(
            'status-filter'
        );


    const summaryTotal =
        document.getElementById(
            'summary-total'
        );


    const summaryEmployees =
        document.getElementById(
            'summary-employees'
        );


    const summaryActive =
        document.getElementById(
            'summary-active'
        );


    const summaryInactive =
        document.getElementById(
            'summary-inactive'
        );


    let usersData = [];


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


    function showMessage(
        text,
        type = 'success'
    ) {

        pageMessage.textContent =
            text;


        pageMessage.className =
            `users-message ${type}`;


        pageMessage.style.display =
            'block';


        pageMessage.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }


    function hideMessage() {

        pageMessage.style.display =
            'none';
    }


    function getErrors(result) {

        if (result.errors) {

            return Object
                .values(result.errors)
                .flat()
                .join(' ');
        }


        return result.message
            ?? 'Ocurrió un error inesperado.';
    }


    /*
    |--------------------------------------------------------------------------
    | CARGAR USUARIOS
    |--------------------------------------------------------------------------
    */

    async function loadUsers() {

        usersLoading.style.display =
            'block';


        usersEmpty.style.display =
            'none';


        usersTableContainer.style.display =
            'none';


        try {

            const response =
                await fetch(
                    '/api/users',
                    {
                        headers: {

                            'Accept':
                                'application/json',

                            'Authorization':
                                `Bearer ${usersToken}`
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

                usersLoading.style.display =
                    'none';


                showMessage(
                    result.message
                    ?? 'No fue posible cargar los usuarios.',
                    'error'
                );

                return;
            }


            usersData =
                result.data ?? [];


            updateSummary();

            renderUsers();


        } catch (error) {

            usersLoading.style.display =
                'none';


            showMessage(
                'No fue posible conectar con el servidor.',
                'error'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESUMEN
    |--------------------------------------------------------------------------
    */

    function updateSummary() {

        const employees =
            usersData.filter(
                user =>
                    user.role?.name ===
                    'employee'
            );


        const activeEmployees =
            employees.filter(
                user =>
                    user.status ===
                    'active'
            );


        const inactiveEmployees =
            employees.filter(
                user =>
                    user.status ===
                    'inactive'
            );


        summaryTotal.textContent =
            usersData.length;


        summaryEmployees.textContent =
            employees.length;


        summaryActive.textContent =
            activeEmployees.length;


        summaryInactive.textContent =
            inactiveEmployees.length;
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR USUARIOS
    |--------------------------------------------------------------------------
    */

    function renderUsers() {

        usersLoading.style.display =
            'none';


        usersTableBody.innerHTML =
            '';


        const search =
            userSearch.value
                .trim()
                .toLowerCase();


        const selectedStatus =
            statusFilter.value;


        const filteredUsers =
            usersData.filter(
                user => {

                    const matchesSearch =
                        !search
                        ||
                        user.name
                            ?.toLowerCase()
                            .includes(search)
                        ||
                        user.email
                            ?.toLowerCase()
                            .includes(search);


                    const matchesStatus =
                        !selectedStatus
                        ||
                        user.status ===
                        selectedStatus;


                    return (
                        matchesSearch
                        && matchesStatus
                    );
                }
            );


        usersCount.textContent =
            `${filteredUsers.length} ${
                filteredUsers.length === 1
                    ? 'usuario'
                    : 'usuarios'
            }`;


        if (!filteredUsers.length) {

            usersEmpty.style.display =
                'block';


            usersTableContainer.style.display =
                'none';


            return;
        }


        usersEmpty.style.display =
            'none';


        usersTableContainer.style.display =
            'block';


        filteredUsers.forEach(
            user => {

                const row =
                    document.createElement(
                        'tr'
                    );


                const initial =
                    user.name
                        ?.trim()
                        .charAt(0)
                        .toUpperCase()
                    || 'U';


                const roleClass =
                    user.role?.name ===
                    'company_admin'
                        ? 'badge-admin'
                        : 'badge-employee';


                const verificationClass =
                    user.email_verified
                        ? 'badge-verified'
                        : 'badge-pending';


                const verificationLabel =
                    user.email_verified
                        ? 'Verificado'
                        : 'Pendiente';


                const statusClass =
                    user.status === 'active'
                        ? 'badge-active'
                        : 'badge-inactive';


                const statusLabel =
                    user.status === 'active'
                        ? 'Activo'
                        : 'Inactivo';


                row.innerHTML = `

                    <td>

                        <div class="user-cell">

                            <div class="user-initial">
                                ${escapeHtml(initial)}
                            </div>


                            <div class="user-details">

                                <strong>
                                    ${escapeHtml(user.name)}
                                </strong>

                                <span>
                                    ${escapeHtml(user.email)}
                                </span>

                                ${
                                    user.is_current_user
                                        ? `
                                            <small class="current-user-text">
                                                Tu cuenta
                                            </small>
                                        `
                                        : ''
                                }

                            </div>

                        </div>

                    </td>


                    <td>

                        <span
                            class="badge ${roleClass}"
                        >
                            ${escapeHtml(
                                user.role?.label
                                ?? 'Sin rol'
                            )}
                        </span>

                    </td>


                    <td>

                        <span
                            class="badge ${verificationClass}"
                        >
                            ${verificationLabel}
                        </span>

                    </td>


                    <td>

                        <span
                            class="badge ${statusClass}"
                        >
                            ${statusLabel}
                        </span>

                    </td>


                    <td>

                        <div
                            class="table-actions"
                            data-actions
                        ></div>

                    </td>
                `;


                const actions =
                    row.querySelector(
                        '[data-actions]'
                    );


                if (user.can_manage) {

                    const editButton =
                        document.createElement(
                            'button'
                        );


                    editButton.type =
                        'button';


                    editButton.className =
                        'table-action';


                    editButton.textContent =
                        'Editar';


                    editButton.addEventListener(
                        'click',
                        () =>
                            startEditUser(
                                user
                            )
                    );


                    actions.appendChild(
                        editButton
                    );


                    const statusButton =
                        document.createElement(
                            'button'
                        );


                    statusButton.type =
                        'button';


                    statusButton.className =
                        user.status === 'active'
                            ? 'table-action status'
                            : 'table-action activate';


                    statusButton.textContent =
                        user.status === 'active'
                            ? 'Desactivar'
                            : 'Activar';


                    statusButton.addEventListener(
                        'click',
                        () =>
                            changeUserStatus(
                                user
                            )
                    );


                    actions.appendChild(
                        statusButton
                    );

                } else {

                    const text =
                        document.createElement(
                            'span'
                        );


                    text.className =
                        'table-current';


                    text.textContent =
                        user.is_current_user
                            ? 'Cuenta actual'
                            : 'Sin acciones';


                    actions.appendChild(
                        text
                    );
                }


                usersTableBody.appendChild(
                    row
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REGISTRAR / EDITAR
    |--------------------------------------------------------------------------
    */

    userForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            hideMessage();


            const editing =
                Boolean(
                    userId.value
                );


            const payload = {

                name:
                    userName.value.trim(),

                email:
                    userEmail.value
                        .trim()
                        .toLowerCase()
            };


            if (!editing) {

                payload.password =
                    userPassword.value;


                payload.password_confirmation =
                    userPasswordConfirmation.value;


                if (
                    payload.password !==
                    payload.password_confirmation
                ) {

                    showMessage(
                        'Las contraseñas no coinciden.',
                        'error'
                    );

                    return;
                }
            }


            const url =
                editing
                    ? `/api/users/${userId.value}`
                    : '/api/users';


            const method =
                editing
                    ? 'PUT'
                    : 'POST';


            saveUserButton.disabled =
                true;


            saveUserButton.textContent =
                editing
                    ? 'Guardando...'
                    : 'Registrando...';


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
                                    `Bearer ${usersToken}`
                            },

                            body:
                                JSON.stringify(
                                    payload
                                )
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
                        getErrors(result),
                        'error'
                    );

                    return;
                }


                resetUserForm();


                showMessage(
                    result.message
                    ?? 'Operación realizada correctamente.'
                );


                await loadUsers();


            } catch (error) {

                showMessage(
                    'No fue posible conectar con el servidor.',
                    'error'
                );

            } finally {

                saveUserButton.disabled =
                    false;


                if (!userId.value) {

                    saveUserButton.textContent =
                        'Registrar empleado';
                }
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | EDITAR
    |--------------------------------------------------------------------------
    */

    function startEditUser(user) {

        hideMessage();


        userId.value =
            user.id;


        userName.value =
            user.name ?? '';


        userEmail.value =
            user.email ?? '';


        passwordFields.style.display =
            'none';


        userPassword.required =
            false;


        userPasswordConfirmation.required =
            false;


        formTitle.textContent =
            'Editar empleado';


        formDescription.textContent =
            'Actualiza el nombre o correo del empleado.';


        saveUserButton.textContent =
            'Guardar cambios';


        cancelEditButton.style.display =
            'inline-flex';


        userForm.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }


    /*
    |--------------------------------------------------------------------------
    | RESTABLECER FORMULARIO
    |--------------------------------------------------------------------------
    */

    function resetUserForm() {

        userForm.reset();


        userId.value =
            '';


        passwordFields.style.display =
            'block';


        userPassword.required =
            true;


        userPasswordConfirmation.required =
            true;


        formTitle.textContent =
            'Registrar empleado';


        formDescription.textContent =
            'Crea una cuenta para un empleado de tu empresa.';


        saveUserButton.textContent =
            'Registrar empleado';


        saveUserButton.disabled =
            false;


        cancelEditButton.style.display =
            'none';
    }


    cancelEditButton.addEventListener(
        'click',
        function () {

            resetUserForm();

            hideMessage();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | ESTADO
    |--------------------------------------------------------------------------
    */

    async function changeUserStatus(user) {

        const newStatus =
            user.status === 'active'
                ? 'inactive'
                : 'active';


        const action =
            newStatus === 'active'
                ? 'activar'
                : 'desactivar';


        if (
            !confirm(
                `¿Deseas ${action} a "${user.name}"?`
            )
        ) {
            return;
        }


        hideMessage();


        try {

            const response =
                await fetch(
                    `/api/users/${user.id}/status`,
                    {
                        method: 'PATCH',

                        headers: {

                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'Authorization':
                                `Bearer ${usersToken}`
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


            if (
                logoutIfUnauthorized(
                    response
                )
            ) {
                return;
            }


            if (!response.ok) {

                showMessage(
                    getErrors(result),
                    'error'
                );

                return;
            }


            showMessage(
                result.message
                ?? 'Estado actualizado correctamente.'
            );


            await loadUsers();


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

    userSearch.addEventListener(
        'input',
        renderUsers
    );


    statusFilter.addEventListener(
        'change',
        renderUsers
    );


    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    loadUsers();

</script>

@endpush