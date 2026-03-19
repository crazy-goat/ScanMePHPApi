<?php
namespace App\Middleware;

use App\Service\OpenTelemetryService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class OpenTelemetryMiddleware implements MiddlewareInterface
{
    private OpenTelemetryService $otelService;

    public function __construct()
    {
        $this->otelService = OpenTelemetryService::getInstance();
    }

    public function process(Request $request, callable $handler): Response
    {
        error_log("[OTel] Middleware started - OTel enabled: " . ($this->otelService->isEnabled() ? 'YES' : 'NO'));
        
        if (!$this->otelService->isEnabled()) {
            error_log("[OTel] Skipped - OTel not enabled");
            return $handler($request);
        }

        $tracer = $this->otelService->getTracer();
        if ($tracer === null) {
            error_log("[OTel] Skipped - tracer not available");
            return $handler($request);
        }

        error_log("[OTel] Creating span for: " . $request->method() . " " . $request->path());

        $span = $tracer->spanBuilder('http_request')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->method())
            ->setAttribute('http.url', $request->fullUrl())
            ->setAttribute('http.scheme', $request->protocolVersion() ? 'HTTP/' . $request->protocolVersion() : 'HTTP/1.1')
            ->setAttribute('http.host', $request->host())
            ->setAttribute('http.target', $request->path())
            ->setAttribute('http.user_agent', $request->header('user-agent') ?? '')
            ->setAttribute('http.request_content_length', $request->header('content-length') ?? 0)
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $handler($request);
            
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $body = $response->rawBody();
            $span->setAttribute('http.response_content_length', $body ? strlen($body) : 0);
            
            if ($response->getStatusCode() >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            error_log("[OTel] Span ended successfully - Status: " . $response->getStatusCode());
            
            return $response;
        } catch (\Throwable $e) {
            error_log("[OTel] Span ended with error: " . $e->getMessage());
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
            // Force flush to ensure span is sent immediately
            OpenTelemetryService::getInstance()->shutdown();
            error_log("[OTel] Flushed to collector");
        }
    }
}
