<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('CLAUDE_MAX_TOKENS', 8192),
        'max_context_tokens' => env('CLAUDE_MAX_CONTEXT_TOKENS', 200000),
        'chunk_size' => env('CLAUDE_CHUNK_SIZE', 95000), // Safe chunk size under 100k
    ],

    'unstructured' => [
        'url' => env('UNSTRUCTURED_API_URL', 'http://34.48.24.135:8888'),
        'timeout' => env('UNSTRUCTURED_API_TIMEOUT', 300),
    ],

    'qdrant' => [
        'url' => env('QDRANT_URL', 'https://37bb1fc7-5984-493f-ba34-f5af4d92b2e2.europe-west3-0.gcp.cloud.qdrant.io:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'bvi_customs_tariffs'),
        'timeout' => env('QDRANT_TIMEOUT', 30),
    ],

    'openai_embeddings' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => env('OPENAI_EMBEDDING_BATCH_SIZE', 100),
    ],

];
