<?php

namespace App\Services\Chat;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Asistente de LumaTek respaldado por Claude (Anthropic).
 */
class ClaudeChatAssistant implements ChatAssistant
{
    public const REFUSAL_REPLY =
        'No puedo ayudarte con eso. ¿Te ayudo con tus invernaderos o con algún cultivo?';

    public const EMPTY_REPLY =
        'No pude generar una respuesta completa. ¿Puedes intentarlo de nuevo o hacer la pregunta más concreta?';

    /**
     * @param  ClientInterface|null  $transport  Cliente HTTP; solo se reemplaza en pruebas.
     */
    public function __construct(
        private ?ClientInterface $transport = null
    ) {}

    public function reply(
        array $messages,
        array $snapshot,
        ?UploadedFile $image
    ): string {

        $apiKey = config('services.anthropic.api_key');

        if (blank($apiKey)) {
            Log::error('Falta ANTHROPIC_API_KEY: el asistente no puede responder.');

            throw new AssistantUnavailableException('Falta ANTHROPIC_API_KEY.');
        }

        $timeout = (float) config('services.anthropic.timeout');

        $client = new Client(
            apiKey: $apiKey,
            requestOptions: [
                'timeout' => $timeout,
                'maxRetries' => 1,
                'transporter' => $this->transport
                    ?? new GuzzleClient(['timeout' => $timeout]),
            ],
        );

        try {

            $message = $client->beta->messages->create(
                ...$this->buildRequest($messages, $snapshot, $image)
            );

        } catch (AnthropicException $error) {

            Log::warning(
                'El asistente no pudo obtener respuesta de Claude.',
                ['exception' => $error::class, 'error' => $error->getMessage()]
            );

            throw new AssistantUnavailableException($error->getMessage(), previous: $error);
        }

        if ($message->stopReason === 'refusal') {

            Log::info(
                'Claude rechazó una consulta del asistente.',
                ['category' => $message->stopDetails?->category]
            );

            return self::REFUSAL_REPLY;
        }

        $text = collect($message->content)
            ->filter(fn ($block) => $block->type === 'text')
            ->map(fn ($block) => $block->text)
            ->implode("\n\n");

        return trim($text) === ''
            ? self::EMPTY_REPLY
            : $text;
    }

    /**
     * Argumentos con nombre para `beta->messages->create`.
     */
    public function buildRequest(
        array $messages,
        array $snapshot,
        ?UploadedFile $image
    ): array {

        if ($image) {

            $last = array_key_last($messages);

            $messages[$last]['content'] = [
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'mediaType' => $image->getMimeType(),
                        'data' => base64_encode($image->getContent()),
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => $messages[$last]['content'],
                ],
            ];
        }

        return [
            'model' => config('services.anthropic.model'),
            'maxTokens' => 2048,
            'outputConfig' => ['effort' => 'low'],
            'betas' => ['server-side-fallback-2026-07-01'],
            'fallbacks' => 'default',
            'system' => [
                [
                    'type' => 'text',
                    'text' => file_get_contents(resource_path('prompts/assistant.md')),
                    'cacheControl' => ['type' => 'ephemeral'],
                ],
                [
                    'type' => 'text',
                    'text' => "Resumen actual de la empresa (JSON):\n"
                        .json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                ],
            ],
            'messages' => $messages,
        ];
    }
}
