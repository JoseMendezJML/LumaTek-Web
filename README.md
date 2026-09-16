# LumaTek-Web

Sistema web para la gestión y monitoreo inteligente de invernaderos.

## Descripción

LumaTek es una plataforma web orientada a la gestión y monitoreo de invernaderos. Permitirá administrar empresas, usuarios, invernaderos, zonas y sensores, así como consultar las condiciones ambientales registradas.

El proyecto contempla una arquitectura multiempresa, permitiendo que cada empresa gestione su información de manera independiente.

## Tecnologías

- PHP 8.3
- Laravel 13
- MySQL
- HTML, CSS y JavaScript
- Vite
- Git y GitHub
- Jira para la gestión del proyecto

## Requisitos

Para ejecutar el proyecto se requiere:

- PHP 8.3 o superior
- Composer
- MySQL
- Node.js 20 o superior
- npm

## Instalación

Clonar el repositorio:

    git clone <URL_DEL_REPOSITORIO>

Entrar al proyecto:

    cd LumaTek-Web

Instalar las dependencias de PHP:

    composer install

Instalar las dependencias de Node.js:

    npm install

Crear el archivo de configuración:

    copy .env.example .env

Generar la clave de Laravel:

    php artisan key:generate

Configurar la conexión a la base de datos en el archivo `.env`.

Ejecutar las migraciones:

    php artisan migrate

Iniciar el servidor:

    php artisan serve

## Estructura de ramas

El proyecto utiliza la siguiente estrategia:

- `main`: versión estable del proyecto.
- `develop`: integración de funcionalidades en desarrollo.
- `feature/*`: desarrollo de historias de usuario o funcionalidades específicas.

Ejemplo:

    feature/LUM-56-control-versiones

## Gestión del proyecto

El desarrollo se administra mediante Jira. Las ramas y commits incluyen la clave de la actividad correspondiente para mantener la trazabilidad entre Jira y GitHub.

Ejemplo de commit:

    feat(LUM-56): configurar estructura inicial del proyecto Laravel