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

        $method = $request->method();

        // Start timing
        $startTime = microtime(true);

        // Start span with method-only name; route info is resolved after dispatch
        $span = $tracer->spanBuilder($method)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $method)
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

            // After dispatch, $request->route holds the matched RouteObject
            $httpRoute = $this->resolveRoute($request);
            $spanName = $httpRoute !== null
                ? "$method $httpRoute"
                : "$method {$request->path()}";

            $span->updateName($spanName);

            if ($httpRoute !== null) {
                $span->setAttribute('http.route', $httpRoute);
            }

            $span->setAttribute('http.status_code', $response->getStatusCode());
            $body = $response->rawBody();
            $span->setAttribute('http.response_content_length', $body ? strlen($body) : 0);
            
            if ($response->getStatusCode() >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            // Record metrics with route label for per-endpoint breakdown
            $duration = (microtime(true) - $startTime) * 1000;
            $metricAttributes = [
                'method' => $method,
                'status' => (string) $response->getStatusCode(),
                'route'  => $httpRoute ?? $request->path(),
            ];
            if ($this->requestCounter !== null) {
                $this->requestCounter->add(1, $metricAttributes);
            }
            if ($this->responseTimeHistogram !== null) {
                $this->responseTimeHistogram->record($duration, $metricAttributes);
            }

            if ($logger !== null && $response->getStatusCode() >= 400) {
                $logger->logRecordBuilder()
                    ->setSeverityNumber($response->getStatusCode() >= 500 ? Severity::ERROR : Severity::WARN)
                    ->setBody("$spanName {$response->getStatusCode()}")
                    ->setAttribute('http.method', $method)
                    ->setAttribute('http.route', $httpRoute ?? $request->path())
                    ->setAttribute('http.path', $request->path())
                    ->setAttribute('http.status_code', $response->getStatusCode())
                    ->emit();
            }

            return $response;
        } catch (\Throwable $e) {
            $httpRoute = $this->resolveRoute($request);
            $spanName = $httpRoute !== null
                ? "$method $httpRoute"
                : "$method {$request->path()}";

            $span->updateName($spanName);

            if ($httpRoute !== null) {
                $span->setAttribute('http.route', $httpRoute);
            }

            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            
            // Record error metric
            $duration = (microtime(true) - $startTime) * 1000;
            $metricAttributes = [
                'method' => $method,
                'status' => 'error',
                'route'  => $httpRoute ?? $request->path(),
            ];
            if ($this->requestCounter !== null) {
                $this->requestCounter->add(1, $metricAttributes);
            }
            if ($this->responseTimeHistogram !== null) {
                $this->responseTimeHistogram->record($duration, $metricAttributes);
            }
            
            // Log error
            if ($logger !== null) {
                $logger->logRecordBuilder()
                    ->setSeverityNumber(Severity::ERROR)
                    ->setBody("$spanName error")
                    ->setAttribute('http.method', $method)
                    ->setAttribute('http.route', $httpRoute ?? $request->path())
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

    /**
     * Resolve the route pattern from the request after dispatch.
     *
     * Returns the route template (e.g. "/fonts/{file}") when a named route
     * was matched, or null when the request was handled by a fallback/static
     * file handler or controller-based routing without an explicit route.
     */
    private function resolveRoute(Request $request): ?string
    {
        $route = $request->route;

        if ($route === null) {
            return null;
        }

        $path = $route->getPath();

        return ($path !== '' && $path !== null) ? $path : null;
    }
}
