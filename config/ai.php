<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | framework. You may set this to 'openai', 'huggingface', or others
    | supported by this package.
    |
    */
    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for API responses to save on costs. Set 'enabled' to
    | true to cache responses and set TTL in minutes.
    |
    */
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 60), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Behavior
    |--------------------------------------------------------------------------
    |
    | Configure fallback behavior when a provider fails. Set 'enabled' to
    | true to enable fallback to other providers in the specified order.
    |
    */
    'fallback' => [
        'enabled' => env('AI_FALLBACK_ENABLED', false),
        'providers' => ['openai', 'huggingface'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure API request and response logging. Set 'enabled' to true to
    | log all AI API interactions.
    |
    */
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'channel' => env('AI_LOGGING_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent exceeding provider quotas. Set limits
    | per minute to avoid overcharges.
    |
    */
    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'max_requests_per_minute' => env('AI_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Storage
    |--------------------------------------------------------------------------
    |
    | Configure storage of AI completions and requests in the database.
    | Useful for auditing and analytics.
    |
    */
    'storage' => [
        'enabled' => env('AI_STORAGE_ENABLED', false),
        'purge_after_days' => env('AI_STORAGE_PURGE_AFTER', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your AI provider credentials and settings here.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'options' => [
                'timeout' => env('OPENAI_TIMEOUT', 30),
                'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
                'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
                'default_embedding_model' => env('OPENAI_DEFAULT_EMBEDDING_MODEL', 'text-embedding-3-large'),
                'default_image_model' => env('OPENAI_DEFAULT_IMAGE_MODEL', 'dall-e-3'),
            ],
        ],
        
        'huggingface' => [
            'api_key' => env('HUGGINGFACE_API_KEY'),
            'options' => [
                'timeout' => env('HUGGINGFACE_TIMEOUT', 30),
                'base_uri' => env('HUGGINGFACE_BASE_URI', 'https://api-inference.huggingface.co/models'),
                'default_model' => env('HUGGINGFACE_DEFAULT_MODEL', 'gpt2'),
                'default_embedding_model' => env('HUGGINGFACE_DEFAULT_EMBEDDING_MODEL', 'sentence-transformers/all-mpnet-base-v2'),
            ],
        ],
        
        // Espacio para añadir más proveedores
    ],
];