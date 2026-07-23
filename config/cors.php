<?php

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TGR_ALLOWED_ORIGINS', 'http://127.0.0.1:8081,http://127.0.0.1:8082,http://localhost:8081,http://localhost:8082'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Correlation-ID'],
    'exposed_headers' => ['X-Correlation-ID'],
    'max_age' => 600,
    'supports_credentials' => false,
];
