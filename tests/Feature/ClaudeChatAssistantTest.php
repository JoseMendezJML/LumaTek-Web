<?php

namespace Tests\Feature;

use App\Services\Chat\AssistantUnavailableException;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\ClaudeChatAssistant;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class ClaudeChatAssistantTest extends TestCase
{
    private array $snapshot = ['company' => 'Agro Norte', 'greenhouses' => []];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.anthropic.api_key' => 'test-key',
            'services.anthropic.model' => 'claude-opus-5-5',
        ]);
    }

    private function transport(int $status, array $body): ClientInterface
    {
        return new class($status, $body) implements ClientInterface
        {
            public array $requests = [];

            public function __construct(private int $status, private array $body) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = json_decode((string) $request->getBody(), true);

                return new Response($this->status, ['Content-Type' => 'application/json'], json_encode($this->body));
            }
        };
    }

    private function message(string $stopReason, array $content, ?array $stopDetails = null): array
    {
        return [
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null,
            'stop_details' => $stopDetails,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];
    }

    private function question(): array
    {
        return [['role' => 'user', 'content' => '¿Cómo están mis invernaderos?']];
    }

    public function test_is_bound_as_the_chat_assistant(): void
    {
        $this->assertInstanceOf(ClaudeChatAssistant::class, app(ChatAssistant::class));
    }

    public function test_request_has_cached_prompt_and_snapshot(): void
    {
        $request = (new ClaudeChatAssistant)->buildRequest($this->question(), $this->snapshot, null);

        $this->assertSame('claude-opus-5-5', $request['model']);
        $this->assertSame(2048, $request['maxTokens']);
        $this->assertSame(['effort' => 'low'], $request['outputConfig']);
        $this->assertSame('default', $request['fallbacks']);
        $this->assertSame(['server-side-fallback-2026-07-01'], $request['betas']);
        $this->assertCount(2, $request['system']);
        $this->assertStringContainsString('asistente agronómico de LumaTek', $request['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $request['system'][0]['cacheControl']);
        $this->assertStringContainsString(json_encode($this->snapshot, JSON_UNESCAPED_UNICODE), $request['system'][1]['text']);
        $this->assertSame($this->question(), $request['messages']);
    }

    public function test_image_is_attached_before_the_last_question(): void
    {
        $image = UploadedFile::fake()->image('plaga.png');

        $request = (new ClaudeChatAssistant)->buildRequest($this->question(), $this->snapshot, $image);

        $content = end($request['messages'])['content'];
        $this->assertSame('image', $content[0]['type']);
        $this->assertSame('base64', $content[0]['source']['type']);
        $this->assertSame('image/png', $content[0]['source']['mediaType']);
        $this->assertSame(base64_encode(file_get_contents($image->getRealPath())), $content[0]['source']['data']);
        $this->assertSame(['type' => 'text', 'text' => '¿Cómo están mis invernaderos?'], $content[1]);
    }

    public function test_reply_returns_text(): void
    {
        $transport = $this->transport(200, $this->message('end_turn', [['type' => 'text', 'text' => 'Todo en orden.']]));

        $reply = (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, null);

        $this->assertSame('Todo en orden.', $reply);
        $this->assertSame('claude-opus-5-5', $transport->requests[0]['model']);
    }

    public function test_wire_format_sent_to_the_api(): void
    {
        $transport = $this->transport(200, $this->message('end_turn', [['type' => 'text', 'text' => 'ok']]));

        (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, UploadedFile::fake()->image('plaga.png'));

        $body = $transport->requests[0];
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame(2048, $body['max_tokens']);
        $this->assertSame(['effort' => 'low'], $body['output_config']);
        $this->assertSame(['type' => 'ephemeral'], $body['system'][0]['cache_control']);
        $this->assertSame('image/png', $body['messages'][0]['content'][0]['source']['media_type']);
    }

    public function test_refusal_returns_friendly_message(): void
    {
        $transport = $this->transport(200, $this->message('refusal', [], ['type' => 'refusal', 'category' => 'cyber', 'explanation' => null]));

        $reply = (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, null);

        $this->assertSame(ClaudeChatAssistant::REFUSAL_REPLY, $reply);
    }

    public function test_reply_without_text_returns_fallback(): void
    {
        $transport = $this->transport(200, $this->message('max_tokens', []));

        $reply = (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, null);

        $this->assertSame(ClaudeChatAssistant::EMPTY_REPLY, $reply);
    }

    public function test_api_error_becomes_unavailable(): void
    {
        $transport = $this->transport(400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'bad']]);

        $this->expectException(AssistantUnavailableException::class);

        (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, null);
    }

    public function test_missing_api_key_becomes_unavailable(): void
    {
        config(['services.anthropic.api_key' => null]);
        $transport = $this->transport(200, $this->message('end_turn', [['type' => 'text', 'text' => 'x']]));

        try {
            (new ClaudeChatAssistant($transport))->reply($this->question(), $this->snapshot, null);
            $this->fail('Se esperaba AssistantUnavailableException.');
        } catch (AssistantUnavailableException) {
            $this->assertSame([], $transport->requests);
        }
    }
}
