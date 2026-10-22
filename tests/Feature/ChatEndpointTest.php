<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Chat\AssistantUnavailableException;
use App\Services\Chat\ChatAssistant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class ChatEndpointTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase {
        setUp as setUpDatabase;
    }

    private object $assistant;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $this->assistant = new class implements ChatAssistant
        {
            public array $calls = [];

            public bool $fail = false;

            public function reply(array $messages, array $snapshot, ?UploadedFile $image): string
            {
                $this->calls[] = compact('messages', 'snapshot', 'image');

                if ($this->fail) {
                    throw new AssistantUnavailableException('fallo simulado');
                }

                return 'Hola';
            }
        };

        $this->instance(ChatAssistant::class, $this->assistant);
    }

    private function send(string $messages, ?UploadedFile $image = null): TestResponse
    {
        $payload = ['messages' => $messages];

        if ($image) {
            $payload['image'] = $image;
        }

        return $this->withHeader('Accept', 'application/json')->post('/api/chat', $payload);
    }

    private function question(): string
    {
        return json_encode([['role' => 'user', 'content' => '¿Cómo están mis invernaderos?']]);
    }

    private function loginCompany(?Company $company = null): Company
    {
        $company ??= $this->company(name: 'Agro Norte');
        $this->loginApi($this->user($company));

        return $company;
    }

    public function test_requires_token(): void
    {
        $this->send($this->question())->assertUnauthorized();
        $this->assertCount(0, $this->assistant->calls);
    }

    public function test_rejects_user_without_company(): void
    {
        $this->loginApi($this->user(null, 'superadmin'));

        $this->send($this->question())->assertForbidden();
        $this->assertCount(0, $this->assistant->calls);
    }

    public function test_rejects_suspended_company(): void
    {
        $this->loginApi($this->user($this->company('inactive')));

        $this->send($this->question())->assertForbidden();
        $this->assertCount(0, $this->assistant->calls);
    }

    public static function invalidPayloads(): array
    {
        $user = fn (string $content = 'hola') => ['role' => 'user', 'content' => $content];
        $assistant = ['role' => 'assistant', 'content' => 'respuesta'];
        $many = [];
        for ($i = 0; $i < 21; $i++) {
            $many[] = $i % 2 === 0 ? $user() : $assistant;
        }

        return [
            'empty string' => [''],
            'not json' => ['no-json'],
            'object instead of list' => [json_encode(['a' => 1])],
            'empty list' => [json_encode([])],
            'more than 20' => [json_encode($many)],
            'roles not alternating' => [json_encode([$user(), $user()])],
            'last is assistant' => [json_encode([$user(), $assistant])],
            'first is assistant' => [json_encode([$assistant, $user()])],
            'system role' => [json_encode([['role' => 'system', 'content' => 'x']])],
            'empty content' => [json_encode([$user('')])],
            'content too long' => [json_encode([$user(str_repeat('a', 4001))])],
            'numeric content' => [json_encode([['role' => 'user', 'content' => 123]])],
            'item is not an object' => [json_encode(['hola'])],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_invalid_messages(string $messages): void
    {
        $this->loginCompany();

        $this->send($messages)->assertUnprocessable();
        $this->assertCount(0, $this->assistant->calls);
    }

    public function test_rejects_missing_messages(): void
    {
        $this->loginCompany();

        $this->withHeader('Accept', 'application/json')->post('/api/chat', [])->assertUnprocessable();
    }

    public function test_rejects_gif_image(): void
    {
        $this->loginCompany();

        $this->send($this->question(), UploadedFile::fake()->create('plaga.gif', 10, 'image/gif'))->assertUnprocessable();
    }

    public function test_rejects_image_over_5_mb(): void
    {
        $this->loginCompany();

        $this->send($this->question(), UploadedFile::fake()->image('plaga.jpg')->size(6000))->assertUnprocessable();
    }

    public function test_success(): void
    {
        $company = $this->loginCompany();
        $this->resources($company);

        $this->send($this->question())
            ->assertOk()
            ->assertExactJson(['reply' => 'Hola', 'remaining_today' => 49]);

        $call = $this->assistant->calls[0];
        $this->assertSame('¿Cómo están mis invernaderos?', $call['messages'][0]['content']);
        $this->assertSame('Agro Norte', $call['snapshot']['company']);
        $this->assertSame('Greenhouse', $call['snapshot']['greenhouses'][0]['name']);
        $this->assertNull($call['image']);
    }

    public function test_accepts_a_valid_conversation(): void
    {
        $this->loginCompany();
        $messages = json_encode([
            ['role' => 'user', 'content' => 'hola'],
            ['role' => 'assistant', 'content' => 'hola, ¿en qué te ayudo?'],
            ['role' => 'user', 'content' => '¿qué alertas tengo?'],
        ]);

        $this->send($messages)->assertOk();
        $this->assertCount(3, $this->assistant->calls[0]['messages']);
    }

    public function test_accepts_long_assistant_turns(): void
    {
        $this->loginCompany();
        $messages = json_encode([
            ['role' => 'user', 'content' => 'Dame un diagnóstico detallado.'],
            ['role' => 'assistant', 'content' => str_repeat('a', 9000)],
            ['role' => 'user', 'content' => '¿Y qué hago ahora?'],
        ]);

        $this->send($messages)->assertOk();
    }

    public function test_rejects_assistant_turns_over_20000_characters(): void
    {
        $this->loginCompany();
        $messages = json_encode([
            ['role' => 'user', 'content' => 'hola'],
            ['role' => 'assistant', 'content' => str_repeat('a', 20001)],
            ['role' => 'user', 'content' => 'sigue'],
        ]);

        $this->send($messages)->assertUnprocessable();
    }

    public function test_image_reaches_assistant(): void
    {
        $this->loginCompany();

        $this->send($this->question(), UploadedFile::fake()->image('plaga.png'))->assertOk();

        $this->assertInstanceOf(UploadedFile::class, $this->assistant->calls[0]['image']);
    }

    public function test_daily_limit(): void
    {
        config(['services.anthropic.daily_limit' => 2]);
        $this->loginCompany();

        $this->send($this->question())->assertOk()->assertJsonPath('remaining_today', 1);
        $this->send($this->question())->assertOk()->assertJsonPath('remaining_today', 0);
        $this->send($this->question())
            ->assertStatus(429)
            ->assertJsonPath('code', 'chat_limit_reached');

        $this->assertCount(2, $this->assistant->calls);
    }

    public function test_per_minute_throttle_follows_the_contract(): void
    {
        $this->loginCompany();

        for ($i = 0; $i < 10; $i++) {
            $this->send($this->question())->assertOk();
        }

        $this->send($this->question())
            ->assertStatus(429)
            ->assertJsonPath('code', 'chat_rate_limited')
            ->assertJsonPath('message', 'Estás enviando mensajes muy rápido. Espera un minuto e intenta de nuevo.');
    }

    public function test_limit_is_per_company(): void
    {
        config(['services.anthropic.daily_limit' => 1]);
        $exhausted = $this->company(name: 'Agro Sur');
        Cache::put('chat:'.$exhausted->id.':'.now()->format('Y-m-d'), 1, now()->endOfDay());

        $this->loginCompany();

        $this->send($this->question())->assertOk()->assertJsonPath('remaining_today', 0);
    }

    public function test_limit_resets_next_day(): void
    {
        config(['services.anthropic.daily_limit' => 1]);
        $this->travelTo('2026-10-22 23:50:00');
        $this->loginCompany();
        $this->send($this->question())->assertOk();
        $this->send($this->question())->assertStatus(429);

        $this->travelTo('2026-10-23 00:05:00');
        $this->send($this->question())->assertOk();
    }

    public function test_assistant_failure_does_not_consume_limit(): void
    {
        $this->loginCompany();
        $this->assistant->fail = true;

        $this->send($this->question())
            ->assertStatus(503)
            ->assertJsonPath('code', 'assistant_unavailable');

        $this->assistant->fail = false;
        $this->send($this->question())->assertOk()->assertJsonPath('remaining_today', 49);
    }
}
