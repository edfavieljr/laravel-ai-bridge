<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Exceptions;

use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use PHPUnit\Framework\TestCase;

class AIExceptionTest extends TestCase
{
    public function test_basic_exception(): void
    {
        $exception = new AIException('Test error', 500);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals('general_error', $exception->getErrorType());
        $this->assertNull($exception->getProvider());
        $this->assertNull($exception->getModel());
        $this->assertEmpty($exception->getDetails());
    }

    public function test_exception_with_all_params(): void
    {
        $exception = new AIException(
            'Error msg',
            400,
            null,
            'custom_error',
            'openai',
            'gpt-4',
            ['key' => 'value']
        );

        $this->assertEquals('custom_error', $exception->getErrorType());
        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals('gpt-4', $exception->getModel());
        $this->assertEquals(['key' => 'value'], $exception->getDetails());
    }

    public function test_setters_return_self(): void
    {
        $exception = new AIException('test');

        $this->assertSame($exception, $exception->setErrorType('new_type'));
        $this->assertSame($exception, $exception->setProvider('anthropic'));
        $this->assertSame($exception, $exception->setModel('claude'));
        $this->assertSame($exception, $exception->setDetails(['a' => 'b']));
        $this->assertSame($exception, $exception->addDetail('c', 'd'));
    }

    public function test_add_detail(): void
    {
        $exception = new AIException('test');
        $exception->addDetail('retry_after', 30);
        $exception->addDetail('request_id', 'abc123');

        $details = $exception->getDetails();
        $this->assertEquals(30, $details['retry_after']);
        $this->assertEquals('abc123', $details['request_id']);
    }

    public function test_rate_limit_exceeded_factory(): void
    {
        $exception = AIException::rateLimitExceeded('openai', 60);

        $this->assertEquals(429, $exception->getCode());
        $this->assertEquals('rate_limit_exceeded', $exception->getErrorType());
        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals(60, $exception->getDetails()['retry_after']);
    }

    public function test_authentication_failed_factory(): void
    {
        $exception = AIException::authenticationFailed('anthropic');

        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals('authentication_failed', $exception->getErrorType());
        $this->assertEquals('anthropic', $exception->getProvider());
    }

    public function test_invalid_model_factory(): void
    {
        $exception = AIException::invalidModel('openai', 'gpt-5');

        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals('invalid_model', $exception->getErrorType());
        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals('gpt-5', $exception->getModel());
    }

    public function test_missing_capability_factory(): void
    {
        $exception = AIException::missingCapability('anthropic', 'embeddings');

        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals('missing_capability', $exception->getErrorType());
        $this->assertEquals('anthropic', $exception->getProvider());
        $this->assertEquals('embeddings', $exception->getDetails()['capability']);
        $this->assertStringContainsString('embeddings', $exception->getMessage());
    }

    public function test_service_unavailable_factory(): void
    {
        $exception = AIException::serviceUnavailable('gemini');

        $this->assertEquals(503, $exception->getCode());
        $this->assertEquals('service_unavailable', $exception->getErrorType());
        $this->assertEquals('gemini', $exception->getProvider());
    }

    public function test_context_length_exceeded_factory(): void
    {
        $exception = AIException::contextLengthExceeded('openai', 'gpt-4', 10000, 8192);

        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals('context_length_exceeded', $exception->getErrorType());
        $this->assertEquals(10000, $exception->getDetails()['token_count']);
        $this->assertEquals(8192, $exception->getDetails()['max_tokens']);
    }

    public function test_content_filtered_factory(): void
    {
        $exception = AIException::contentFiltered('openai', 'dall-e-3');

        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals('content_filtered', $exception->getErrorType());
        $this->assertEquals('openai', $exception->getProvider());
        $this->assertEquals('dall-e-3', $exception->getModel());
    }
}
