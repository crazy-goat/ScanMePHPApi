<?php
namespace App\Service;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

class SpanScope
{
    public function __construct(
        private readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
    ) {}

    public function setAttribute(string $key, mixed $value): self
    {
        $this->span->setAttribute($key, $value);
        return $this;
    }

    public function setOk(): void
    {
        $this->span->setStatus(StatusCode::STATUS_OK);
    }

    public function setError(string $message = ''): void
    {
        $this->span->setStatus(StatusCode::STATUS_ERROR, $message);
    }

    public function recordException(\Throwable $e): void
    {
        $this->span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $this->span->recordException($e);
    }

    public function end(): void
    {
        $this->span->end();
        $this->scope->detach();
    }
}
