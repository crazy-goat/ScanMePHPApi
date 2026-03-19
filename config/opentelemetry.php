<?php
return [
    'enabled' => !empty(getenv('OTEL_EXPORTER_OTLP_ENDPOINT')),
    'service' => [
        'name' => getenv('OTEL_SERVICE_NAME') ?: 'scanme-php-api',
        'version' => '1.0.0',
        'namespace' => 'scanme',
    ],
    'exporter' => [
        'endpoint' => getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: null,
        'protocol' => getenv('OTEL_EXPORTER_OTLP_PROTOCOL') ?: 'http/protobuf',
        'timeout' => 30,
    ],
    'traces' => [
        'enabled' => true,
        'sampler' => getenv('OTEL_TRACES_SAMPLER') ?: 'parentbased_always_on',
    ],
    'metrics' => [
        'enabled' => true,
    ],
    'logs' => [
        'enabled' => true,
    ],
];
