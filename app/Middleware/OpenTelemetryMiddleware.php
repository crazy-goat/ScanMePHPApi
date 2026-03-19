<?php
namespace App\Middleware;

use App\Service\OpenTelemetryService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Logs\Severity;
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
        if (!$this->otelService->isEnabled()) {
            return $handler($request);
        }

        $tracer = $this->otelService->getTracer();
        $logger = $this->otelService->getLogger();
        
        if ($tracer === null) {
            return $handler($request);
        }

        // Log request start
        if ($logger !== null) {
            $logger->logRecordBuilder()
                ->setSeverityNumber(Severity::INFO)
                ->setBody('HTTP request started')
                ->setAttribute('http.method', $request->method())
                ->setAttribute('http.path', $request->path())
                ->setAttribute('http.url', $request->fullUrl())
                ->emit();
        }

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

            // Log successful response
            if ($logger !== null) {
                $logger->logRecordBuilder()
                    ->setSeverityNumber(Severity::INFO)
                    ->setBody('HTTP request completed')
                    ->setAttribute('http.method', $request->method())
                    ->setAttribute('http.path', $request->path())
                    ->setAttribute('http.status_code', $response->getStatusCode())
                    ->emit();
            }
            
            return $response;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            
            // Log error
            if ($logger !== null) {
                $logger->logRecordBuilder()
                    ->setSeverityNumber(Severity::ERROR)
                    ->setBody('HTTP request failed')
                    ->setAttribute('http.method', $request->method())
                    ->setAttribute('http.path', $request->path())
                    ->setAttribute('error', $e->getMessage())
                    ->emit();
            }
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
