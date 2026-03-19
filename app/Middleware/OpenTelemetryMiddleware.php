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
    private ?\OpenTelemetry\API\Metrics\CounterInterface $requestCounter = null;
    private ?\OpenTelemetry\API\Metrics\HistogramInterface $responseTimeHistogram = null;

    public function __construct()
    {
        $this->otelService = OpenTelemetryService::getInstance();
        $meter = $this->otelService->getMeter();
        if ($meter !== null) {
            $this->requestCounter = $meter->createCounter('http_requests_total', 'count', 'Total number of HTTP requests');
            $this->responseTimeHistogram = $meter->createHistogram('http_response_time_ms', 'ms', 'HTTP response time in milliseconds');
        }
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

        // Start timing
        $startTime = microtime(true);

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

            // Record metrics
            $duration = (microtime(true) - $startTime) * 1000;
            if ($this->requestCounter !== null) {
                $this->requestCounter->add(1, ['method' => $request->method(), 'status' => (string) $response->getStatusCode()]);
            }
            if ($this->responseTimeHistogram !== null) {
                $this->responseTimeHistogram->record($duration, ['method' => $request->method(), 'status' => (string) $response->getStatusCode()]);
            }

            if ($logger !== null && $response->getStatusCode() >= 400) {
                $logger->logRecordBuilder()
                    ->setSeverityNumber($response->getStatusCode() >= 500 ? Severity::ERROR : Severity::WARN)
                    ->setBody('HTTP request failed')
                    ->setAttribute('http.method', $request->method())
                    ->setAttribute('http.path', $request->path())
                    ->setAttribute('http.status_code', $response->getStatusCode())
                    ->emit();
            }

            return $response;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            
            // Record error metric
            $duration = (microtime(true) - $startTime) * 1000;
            if ($this->requestCounter !== null) {
                $this->requestCounter->add(1, ['method' => $request->method(), 'status' => 'error']);
            }
            if ($this->responseTimeHistogram !== null) {
                $this->responseTimeHistogram->record($duration, ['method' => $request->method(), 'status' => 'error']);
            }
            
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
