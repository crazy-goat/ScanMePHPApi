<?php
namespace App\Service;

use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

class Telemetry
{
    private static function otel(): OpenTelemetryService
    {
        return OpenTelemetryService::getInstance();
    }

    public static function startSpan(string $name, array $attributes = []): ?SpanScope
    {
        $tracer = self::otel()->getTracer();
        if ($tracer === null) {
            return null;
        }

        $builder = $tracer->spanBuilder($name);
        foreach ($attributes as $key => $value) {
            $builder->setAttribute($key, $value);
        }

        $span = $builder->startSpan();
        $scope = $span->activate();

        return new SpanScope($span, $scope);
    }

    public static function warn(string $message, array $attributes = []): void
    {
        self::log(Severity::WARN, $message, $attributes);
    }

    public static function error(string $message, array $attributes = []): void
    {
        self::log(Severity::ERROR, $message, $attributes);
    }

    private static function log(Severity $severity, string $message, array $attributes): void
    {
        $logger = self::otel()->getLogger();
        if ($logger === null) {
            return;
        }

        $builder = $logger->logRecordBuilder()
            ->setSeverityNumber($severity)
            ->setBody($message);

        foreach ($attributes as $key => $value) {
            $builder->setAttribute($key, $value);
        }

        $builder->emit();
    }
}
