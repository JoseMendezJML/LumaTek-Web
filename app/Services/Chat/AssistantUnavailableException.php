<?php

namespace App\Services\Chat;

use RuntimeException;

/**
 * El asistente no pudo responder (red, proveedor o configuración).
 */
class AssistantUnavailableException extends RuntimeException
{
}
