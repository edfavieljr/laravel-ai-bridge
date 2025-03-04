<?php

namespace YourVendor\LaravelAIBridge\Exceptions;

use Exception;

class AIException extends Exception
{
    /**
     * The error type or category.
     *
     * @var string
     */
    protected $errorType;

    /**
     * The provider that caused the exception.
     *
     * @var string|null
     */
    protected $provider;

    /**
     * The model that caused the exception.
     *
     * @var string|null
     */
    protected $model;

    /**
     * Additional error details.
     *
     * @var array
     */
    protected $details = [];

    /**
     * Create a new AI exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $errorType
     * @param string|null $provider
     * @param string|null $model
     * @param array $details
     */
    public function __construct(
        string $message, 
        int $code = 0, 
        \Throwable $previous = null, 
        string $errorType = null,
        string $provider = null,
        string $model = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->errorType = $errorType ?? 'general_error';
        $this->provider = $provider;
        $this->model = $model;
        $this->details = $details;
    }

    /**
     * Get the error type.
     *
     * @return string
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Set the error type.
     *
     * @param string $errorType
     * @return $this
     */
    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;
        return $this;
    }

    /**
     * Get the provider that caused the exception.
     *
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Set the provider.
     *
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Get the model that caused the exception.
     *
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Set the model.
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the additional error details.
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Set the additional error details.
     *
     * @param array $details
     * @return $this
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
     * Add a detail to the error details.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addDetail(string $key, $value): self
    {
        $this->details[$key] = $value;
        return $this;
    }

    /**
     * Create an exception for API rate limiting.
     *
     * @param string $provider
     * @param int $retryAfter
     * @param string $message
     * @return static
     */
    public static function rateLimitExceeded(string $provider, int $retryAfter = 0, string $message = 'API rate limit exceeded'): self
    {
        $exception = new static(
            $message,
            429,
            null,
            'rate_limit_exceeded',
            $provider
        );

        if ($retryAfter > 0) {
            $exception->addDetail('retry_after', $retryAfter);
        }

        return $exception;
    }

    /**
     * Create an exception for API authentication failure.
     *
     * @param string $provider
     * @param string $message
     * @return static
     */
    public static function authenticationFailed(string $provider, string $message = 'API authentication failed'): self
    {
        return new static(
            $message,
            401,
            null,
            'authentication_failed',
            $provider
        );
    }

    /**
     * Create an exception for invalid model.
     *
     * @param string $provider
     * @param string $model
     * @param string $message
     * @return static
     */
    public static function invalidModel(string $provider, string $model, string $message = 'Invalid model requested'): self
    {
        return new static(
            $message,
            400,
            null,
            'invalid_model',
            $provider,
            $model
        );
    }

    /**
     * Create an exception for missing capability.
     *
     * @param string $provider
     * @param string $capability
     * @param string $message
     * @return static
     */
    public static function missingCapability(string $provider, string $capability, string $message = null): self
    {
        $message = $message ?? "The provider {$provider} does not support the {$capability} capability";
        
        $exception = new static(
            $message,
            400,
            null,
            'missing_capability',
            $provider
        );
        
        $exception->addDetail('capability', $capability);
        
        return $exception;
    }

    /**
     * Create an exception for service unavailable.
     *
     * @param string $provider
     * @param string $message
     * @return static
     */
    public static function serviceUnavailable(string $provider, string $message = 'Service is currently unavailable'): self
    {
        return new static(
            $message,
            503,
            null,
            'service_unavailable',
            $provider
        );
    }

    /**
     * Create an exception for context length exceeded.
     *
     * @param string $provider
     * @param string $model
     * @param int $tokenCount
     * @param int $maxTokens
     * @param string $message
     * @return static
     */
    public static function contextLengthExceeded(string $provider, string $model, int $tokenCount, int $maxTokens, string $message = null): self
    {
        $message = $message ?? "Input exceeds maximum context length ({$tokenCount} > {$maxTokens})";
        
        $exception = new static(
            $message,
            400,
            null,
            'context_length_exceeded',
            $provider,
            $model
        );
        
        $exception->addDetail('token_count', $tokenCount);
        $exception->addDetail('max_tokens', $maxTokens);
        
        return $exception;
    }

    /**
     * Create an exception for content filtering.
     *
     * @param string $provider
     * @param string $model
     * @param string $message
     * @return static
     */
    public static function contentFiltered(string $provider, string $model, string $message = 'Content was filtered due to content policy'): self
    {
        return new static(
            $message,
            400,
            null,
            'content_filtered',
            $provider,
            $model
        );
    }
}