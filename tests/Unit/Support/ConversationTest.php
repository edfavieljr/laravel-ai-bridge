<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Support;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Support\Conversation;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class ConversationTest extends TestCase
{
    public function test_conversation_can_be_created_from_facade(): void
    {
        AI::fake();

        $conversation = AI::conversation();

        $this->assertInstanceOf(Conversation::class, $conversation);
    }

    public function test_send_uses_generate_chat_not_generate_text(): void
    {
        $fake = AI::fake(['generateChat' => 'chat response']);

        $conversation = new Conversation(AI::getFacadeRoot());
        $response = $conversation->send('Hello');

        $this->assertEquals('chat response', $response);
        $fake->assertCalled('generateChat');
        $fake->assertNotCalled('generateText');
    }

    public function test_send_passes_native_messages_array(): void
    {
        $fake = AI::fake(['generateChat' => 'response']);

        $conversation = new Conversation(AI::getFacadeRoot());
        $conversation->send('What is AI?');

        $fake->assertCalledWith('generateChat', function (array $messages) {
            return count($messages) === 1
                && $messages[0]['role'] === 'user'
                && $messages[0]['content'] === 'What is AI?';
        });
    }

    public function test_system_prompt_passed_in_options(): void
    {
        $fake = AI::fake(['generateChat' => 'response']);

        $conversation = new Conversation(AI::getFacadeRoot());
        $conversation->system('You are helpful.');
        $conversation->send('Hello');

        $fake->assertCalledWith('generateChat', function (array $messages, array $options) {
            return $options['system'] === 'You are helpful.';
        });
    }

    public function test_multi_turn_builds_messages_array(): void
    {
        $turn = 0;
        AI::fake([
            'generateChat' => function () use (&$turn) {
                $turn++;
                return "Response {$turn}";
            },
        ]);

        $conversation = new Conversation(AI::getFacadeRoot());

        $r1 = $conversation->send('First');
        $r2 = $conversation->reply('Second');

        $this->assertEquals('Response 1', $r1);
        $this->assertEquals('Response 2', $r2);

        $messages = $conversation->messages();
        $this->assertCount(4, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('First', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Response 1', $messages[1]['content']);
        $this->assertEquals('user', $messages[2]['role']);
        $this->assertEquals('Second', $messages[2]['content']);
        $this->assertEquals('assistant', $messages[3]['role']);
        $this->assertEquals('Response 2', $messages[3]['content']);
    }

    public function test_get_system_prompt(): void
    {
        $conversation = new Conversation();
        $this->assertNull($conversation->getSystemPrompt());

        $conversation->system('Be concise.');
        $this->assertEquals('Be concise.', $conversation->getSystemPrompt());
    }

    public function test_reset_clears_messages(): void
    {
        AI::fake(['generateChat' => 'response']);

        $conversation = new Conversation(AI::getFacadeRoot());
        $conversation->send('Hello');

        $this->assertCount(2, $conversation->messages());

        $conversation->reset();

        $this->assertCount(0, $conversation->messages());
    }

    public function test_with_options_passes_to_service(): void
    {
        $fake = AI::fake(['generateChat' => 'ok']);

        $conversation = new Conversation(AI::getFacadeRoot());
        $conversation->withOptions(['model' => 'gpt-4o-mini']);
        $conversation->send('Hello');

        $fake->assertCalledWith('generateChat', function (array $messages, array $options) {
            return $options['model'] === 'gpt-4o-mini';
        });
    }

    public function test_generate_chat_with_real_openai_format(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => 'Native chat response']]],
            ], 200),
        ]);

        $service = new \Edfavieljr\LaravelAIBridge\Services\OpenAIService(
            'test-key', null, ['default_model' => 'gpt-4o']
        );

        $result = $service->generateChat([
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
            ['role' => 'user', 'content' => 'How are you?'],
        ]);

        $this->assertEquals('Native chat response', $result);

        // Verify native messages array was sent, not concatenated string
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return count($body['messages']) === 3
                && $body['messages'][0]['role'] === 'user'
                && $body['messages'][1]['role'] === 'assistant'
                && $body['messages'][2]['role'] === 'user';
        });
    }

    public function test_generate_chat_with_system_prepends_to_openai(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ], 200),
        ]);

        $service = new \Edfavieljr\LaravelAIBridge\Services\OpenAIService(
            'test-key', null, ['default_model' => 'gpt-4o']
        );

        $service->generateChat(
            [['role' => 'user', 'content' => 'Hi']],
            ['system' => 'You are a tutor.']
        );

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['messages'][0]['role'] === 'system'
                && $body['messages'][0]['content'] === 'You are a tutor.'
                && $body['messages'][1]['role'] === 'user';
        });
    }
}
