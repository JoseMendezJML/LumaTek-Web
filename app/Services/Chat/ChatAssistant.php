<?php

namespace App\Services\Chat;

use Illuminate\Http\UploadedFile;

/**
 * Genera la respuesta del asistente de LumaTek.
 */
interface ChatAssistant
{
    /**
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages
     * @param  array  $snapshot  Resumen de la empresa (GreenhouseSnapshotService::forAssistant).
     *
     * @throws AssistantUnavailableException
     */
    public function reply(array $messages, array $snapshot, ?UploadedFile $image): string;
}
