<?php

return [
    'superadmin' => [
        'interactive_required' => 'Se requiere una terminal interactiva autorizada. No se permite la ejecución automática.',
        'already_exists' => 'Ya existe un superadministrador. Este comando solo permite crear la primera cuenta de plataforma.',
        'role_missing' => 'Debe existir el rol superadmin. Revisa la preparación del esquema y los roles antes de continuar.',
        'email_exists' => 'El correo electrónico ya está registrado. No se modificará ni promoverá la cuenta existente.',
        'name' => 'Nombre del superadministrador',
        'email' => 'Correo electrónico del superadministrador',
        'password' => 'Contraseña (al menos 16 caracteres, mayúsculas, minúsculas, números y símbolos)',
        'password_confirmation' => 'Confirmar contraseña',
        'confirm' => 'Confirmo que verifiqué que :email pertenece al operador autorizado. ¿Crear el primer superadministrador?',
        'cancelled' => 'Creación cancelada. No se creó ninguna cuenta.',
        'created' => 'Superadministrador creado. Inicia sesión desde /login para acceder a /platform.',
        'persistence_failed' => 'No fue posible completar la creación. Comprueba la conexión, el esquema y si la cuenta ya existe antes de reintentar. No se muestran detalles SQL ni credenciales.',
    ],
];
