#!/usr/bin/env php
<?php
/**
 * Quick test script for OpenTelemetry connection
 * Usage: php test-otel.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\OpenTelemetryService;
use OpenTelemetry\API\Trace\StatusCode;

echo "=== OpenTelemetry Test Script ===\n\n";

// Check environment variables
echo "1. Environment Variables:\n";
echo "   OTEL_EXPORTER_OTLP_ENDPOINT: " . (getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'NOT SET') . "\n";
echo "   OTEL_SERVICE_NAME: " . (getenv('OTEL_SERVICE_NAME') ?: 'NOT SET') . "\n";
echo "   OTEL_TRACES_SAMPLER: " . (getenv('OTEL_TRACES_SAMPLER') ?: 'NOT SET') . "\n\n";

// Test OpenTelemetry Service
echo "2. OpenTelemetry Service:\n";
try {
    $service = OpenTelemetryService::getInstance();
    echo "   Service instance: OK\n";
    echo "   OTel enabled: " . ($service->isEnabled() ? 'YES' : 'NO') . "\n";
    echo "   Tracer available: " . ($service->getTracer() !== null ? 'YES' : 'NO') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test sending a trace
echo "3. Sending Test Trace:\n";
$tracer = $service->getTracer();
if ($tracer === null) {
    echo "   Skipped (OTel not enabled)\n";
    echo "\n=== Test Complete ===\n";
    exit(0);
}

try {
    $span = $tracer->spanBuilder('test_script')
        ->setAttribute('test.type', 'manual')
        ->setAttribute('test.timestamp', date('c'))
        ->startSpan();
    
    echo "   Span created: OK\n";
    
    // Simulate some work
    usleep(100000); // 100ms
    
    $span->setAttribute('test.duration_ms', 100);
    $span->setStatus(StatusCode::STATUS_OK, 'Test successful');
    $span->end();
    
    echo "   Span ended: OK\n";
    echo "   Trace ID: " . $span->getContext()->getTraceId() . "\n";
    echo "   Span ID: " . $span->getContext()->getSpanId() . "\n\n";
    
    // Shutdown to flush spans
    echo "4. Flushing spans to collector...\n";
    $service->shutdown();
    echo "   Done!\n\n";
    
    echo "=== Test Complete ===\n";
    echo "Check your observability platform for the trace.\n";
    
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
