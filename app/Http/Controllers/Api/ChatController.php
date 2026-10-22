<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Chat\AssistantUnavailableException;
use App\Services\Chat\ChatAssistant;
use App\Services\GreenhouseSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Throwable;

/**
 * Asistente de LumaTek, compartido por la web y la app móvil.
 *
 * El historial lo guarda cada cliente y lo envía completo;
 * aquí solo se valida, se aplica el límite diario y se
 * pide la respuesta al asistente.
 */
class ChatController extends Controller
{
    public function send(
        Request $request,
        GreenhouseSnapshotService $snapshots,
        ChatAssistant $assistant
    ): JsonResponse {

        $request->validate([
            'messages' => [
                'required',
                'string',
                'json',
            ],

            'image' => [
                'nullable',
                'file',
                'mimes:jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $messages = $this->validatedMessages(
            json_decode($request->input('messages'), true)
        );


        /*
        |--------------------------------------------------------------------------
        | Límite diario por empresa
        |--------------------------------------------------------------------------
        */

        $company = $request->user()->company;

        $limit = (int) config('services.anthropic.daily_limit');

        $limitKey = 'chat:'.$company->id.':'.now()->format('Y-m-d');

        if ((int) Cache::get($limitKey, 0) >= $limit) {
            return response()->json([
                'code' => 'chat_limit_reached',
                'message' => 'Alcanzaste el límite de mensajes del asistente por hoy. Vuelve a intentarlo mañana.',
            ], 429);
        }


        /*
        |--------------------------------------------------------------------------
        | Respuesta del asistente
        |--------------------------------------------------------------------------
        */

        try {

            $reply = $assistant->reply(
                $messages,
                $this->snapshot($snapshots, $company),
                $request->file('image')
            );

        } catch (AssistantUnavailableException $error) {

            return response()->json([
                'code' => 'assistant_unavailable',
                'message' => 'El asistente no está disponible en este momento. Intenta de nuevo en unos minutos.',
            ], 503);
        }

        Cache::add($limitKey, 0, now()->endOfDay());

        $used = (int) Cache::increment($limitKey);

        return response()->json([
            'reply' => $reply,
            'remaining_today' => max(0, $limit - $used),
        ]);
    }

    /**
     * Valida el historial: 1 a 20 mensajes alternados que empiezan
     * y terminan con el usuario; preguntas de hasta 4000 caracteres.
     */
    private function validatedMessages(
        mixed $messages
    ): array {

        $validator = Validator::make(
            ['messages' => $messages],
            [
                'messages' => ['required', 'array', 'list', 'min:1', 'max:20'],
                'messages.*' => ['array'],
                'messages.*.role' => ['required', 'in:user,assistant'],
                // Las respuestas del asistente pueden ser largas; las preguntas no.
                'messages.*.content' => ['required', 'string', 'max:20000'],
            ]
        );

        $validator->after(function (ValidatorInstance $validator) use ($messages) {

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($messages as $index => $message) {

                if ($message['role'] === 'user' && mb_strlen($message['content']) > 4000) {
                    $validator->errors()->add(
                        'messages',
                        'Cada pregunta puede tener como máximo 4000 caracteres.'
                    );

                    return;
                }

                $expected = $index % 2 === 0 ? 'user' : 'assistant';

                if ($message['role'] !== $expected) {
                    $validator->errors()->add(
                        'messages',
                        'Los mensajes deben alternar entre usuario y asistente, empezando por el usuario.'
                    );

                    return;
                }
            }

            if (end($messages)['role'] !== 'user') {
                $validator->errors()->add(
                    'messages',
                    'El último mensaje debe ser del usuario.'
                );
            }
        });

        return collect($validator->validate()['messages'])
            ->map(fn (array $message) => [
                'role' => $message['role'],
                'content' => $message['content'],
            ])
            ->all();
    }

    /**
     * Resumen de la empresa; si falla, el asistente sigue
     * respondiendo sin datos.
     */
    private function snapshot(
        GreenhouseSnapshotService $snapshots,
        Company $company
    ): array {

        try {

            $company->syncSensorPlanRestrictions();

            return $snapshots->forAssistant($company);

        } catch (Throwable $error) {

            Log::warning(
                'No se pudo construir el resumen para el asistente.',
                ['company_id' => $company->id, 'error' => $error->getMessage()]
            );

            return [
                'company' => $company->name,
                'greenhouses' => [],
                'note' => 'sin datos disponibles',
            ];
        }
    }
}
