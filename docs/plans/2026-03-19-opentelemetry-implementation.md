# OpenTelemetry Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Dodać OpenTelemetry (traces, metrics, logs) do API Webman z opcjonalną aktywacją przez zmienną środowiskową

**Architecture:** Service warstwa inicjalizuje SDK tylko gdy podany endpoint, middleware tworzy root span, QrController dodaje child span z atrybutami QR, wszystko eksportowane przez OTLP

**Tech Stack:** OpenTelemetry PHP SDK, OTLP HTTP Exporter, Webman Middleware

---

## Task 1: Add OpenTelemetry Dependencies to Composer

**Files:**
- Modify: `composer.json`

**Step 1: Add dependencies**

```json
{
    "require": {
        "php": ">=8.1",
        "workerman/webman": "^1.5",
        "crazy-goat/scanmephp": "^0.4.11",
        "open-telemetry/sdk": "^1.0",
        "open-telemetry/exporter-otlp": "^1.0",
        "open-telemetry/opentelemetry-auto-psr15": "^0.0.1",
        "guzzlehttp/guzzle": "^7.0"
    }
}
```

**Step 2: Install dependencies**

```bash
composer install --no-interaction
```

Expected: Installation completes without errors

**Step 3: Verify installation**

```bash
composer show | grep open-telemetry
```

Expected: Lists open-telemetry/sdk, open-telemetry/exporter-otlp

**Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add OpenTelemetry dependencies"
```

---

## Task 2: Create OpenTelemetry Configuration File

**Files:**
- Create: `config/opentelemetry.php`

**Step 1: Write configuration**

```php
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
```

**Step 2: Commit**

```bash
git add config/opentelemetry.php
git commit -m "config: add OpenTelemetry configuration"
```

---

## Task 3: Create OpenTelemetry Service

**Files:**
- Create: `app/Service/OpenTelemetryService.php`

**Step 1: Write service class**

```php
<?php
namespace App\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryService
{
    private static ?self $instance = null;
    private ?TracerProvider $tracerProvider = null;
    private ?TracerInterface $tracer = null;
    private bool $enabled = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $config = require base_path() . '/config/opentelemetry.php';
        
        if (!$config['enabled']) {
            return;
        }

        $this->enabled = true;
        $this->initializeTracer($config);
    }

    private function initializeTracer(array $config): void
    {
        $resource = ResourceInfoFactory::create([
            ResourceAttributes::SERVICE_NAME => $config['service']['name'],
            ResourceAttributes::SERVICE_VERSION => $config['service']['version'],
            ResourceAttributes::SERVICE_NAMESPACE => $config['service']['namespace'] ?? null,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => getenv('APP_ENV') ?: 'production',
        ]);

        $endpoint = $config['exporter']['endpoint'];
        $transport = (new OtlpHttpTransportFactory())->create($endpoint . '/v1/traces', 'application/x-protobuf');
        $exporter = new SpanExporter($transport);

        $sampler = $this->createSampler($config['traces']['sampler']);

        $this->tracerProvider = new TracerProvider(
            [new BatchSpanProcessor($exporter)],
            $sampler,
            $resource
        );

        $this->tracer = $this->tracerProvider->getTracer(
            $config['service']['name'],
            $config['service']['version']
        );
    }

    private function createSampler(string $samplerName): ParentBased
    {
        $rootSampler = match ($samplerName) {
            'always_on' => new AlwaysOnSampler(),
            'always_off' => new \OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler(),
            'traceidratio' => new \OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler(0.5),
            default => new AlwaysOnSampler(),
        };

        return new ParentBased($rootSampler);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTracer(): ?TracerInterface
    {
        return $this->tracer;
    }

    public function shutdown(): void
    {
        if ($this->tracerProvider !== null) {
            $this->tracerProvider->shutdown();
        }
    }
}
```

**Step 2: Commit**

```bash
git add app/Service/OpenTelemetryService.php
git commit -m "feat: add OpenTelemetry service for SDK initialization"
```

---

## Task 4: Create OpenTelemetry Middleware

**Files:**
- Create: `app/Middleware/OpenTelemetryMiddleware.php`

**Step 1: Write middleware**

```php
<?php
namespace App\Middleware;

use App\Service\OpenTelemetryService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use support\Request;
use support\Response;
use Webman\MiddlewareInterface;
use Webman\HttpContext;

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
        if ($tracer === null) {
            return $handler($request);
        }

        $span = $tracer->spanBuilder('http_request')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->method())
            ->setAttribute('http.url', $request->url())
            ->setAttribute('http.scheme', $request->scheme())
            ->setAttribute('http.host', $request->host())
            ->setAttribute('http.target', $request->path())
            ->setAttribute('http.user_agent', $request->header('user-agent') ?? '')
            ->setAttribute('http.request_content_length', $request->header('content-length') ?? 0)
            ->startSpan();

        $scope = $span->activate();
        HttpContext::set('otel_span', $span);

        try {
            $response = $handler($request);
            
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setAttribute('http.response_content_length', strlen($response->rawBody()));
            
            if ($response->getStatusCode() >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            return $response;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
```

**Step 2: Commit**

```bash
git add app/Middleware/OpenTelemetryMiddleware.php
git commit -m "feat: add OpenTelemetry middleware for HTTP request tracing"
```

---

## Task 5: Register Middleware in Configuration

**Files:**
- Modify: `config/middleware.php`

**Step 1: Read current middleware config**

```bash
cat config/middleware.php
```

**Step 2: Add OpenTelemetry middleware**

Add to the array:
```php
<?php
return [
    '' => [
        \App\Middleware\OpenTelemetryMiddleware::class,
    ],
];
```

**Step 3: Commit**

```bash
git add config/middleware.php
git commit -m "config: register OpenTelemetry middleware"
```

---

## Task 6: Add Manual Instrumentation to QrController

**Files:**
- Modify: `app/Controller/QrController.php`

**Step 1: Add imports**

```php
use App\Service\OpenTelemetryService;
use OpenTelemetry\API\Trace\StatusCode;
```

**Step 2: Modify __invoke method**

Add at the beginning:
```php
$tracer = OpenTelemetryService::getInstance()->getTracer();
$qrSpan = null;
$scope = null;

if ($tracer !== null) {
    $qrSpan = $tracer->spanBuilder('qr_generation')
        ->setAttribute('qr.format', $format)
        ->setAttribute('qr.size', $size)
        ->setAttribute('qr.ecc', $request->get('ecc', self::DEFAULT_ECC))
        ->setAttribute('qr.margin', $margin)
        ->setAttribute('qr.module_style', $moduleStyle)
        ->setAttribute('qr.type', $type)
        ->setAttribute('qr.has_label', !empty($label))
        ->setAttribute('qr.invert', $invert)
        ->startSpan();
    $scope = $qrSpan->activate();
}
```

Wrap try-catch:
```php
try {
    $qr = new QRCode($decoded, $config);
    $content = $qr->render();
    
    if ($qrSpan !== null) {
        $qrSpan->setAttribute('qr.content_length', strlen($content));
        $qrSpan->setStatus(StatusCode::STATUS_OK);
    }
} catch (\CrazyGoat\ScanMePHP\Exception\InvalidDataException $e) {
    if ($qrSpan !== null) {
        $qrSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $qrSpan->recordException($e);
    }
    // ... existing error handling
} catch (\Throwable $e) {
    if ($qrSpan !== null) {
        $qrSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $qrSpan->recordException($e);
    }
    // ... existing error handling
} finally {
    if ($qrSpan !== null) {
        $qrSpan->end();
        $scope?->detach();
    }
}
```

**Step 3: Commit**

```bash
git add app/Controller/QrController.php
git commit -m "feat: add manual instrumentation to QR generation"
```

---

## Task 7: Add Shutdown Hook

**Files:**
- Modify: `start.php`

**Step 1: Add shutdown hook**

Find the Worker initialization and add:
```php
use App\Service\OpenTelemetryService;

// ... existing code

$worker->onWorkerStop = function () {
    OpenTelemetryService::getInstance()->shutdown();
};
```

**Step 2: Commit**

```bash
git add start.php
git commit -m "feat: add OTel shutdown hook on worker stop"
```

---

## Task 8: Test Implementation

**Files:**
- Test manually via curl

**Step 1: Start application without OTel**

```bash
php start.php start -d
```

Expected: App starts normally without OTel endpoint

**Step 2: Test QR endpoint**

```bash
curl "http://localhost:8787/qr?data=aHR0cHM6Ly9leGFtcGxlLmNvbQ&format=svg"
```

Expected: Returns SVG QR code (200 OK)

**Step 3: Stop application**

```bash
php start.php stop
```

**Step 4: Test with OTel enabled (requires collector)**

```bash
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318 php start.php start -d
```

If you have a local collector running, traces should be exported.

**Step 5: Commit test results**

```bash
git add -A
git commit -m "test: verify OpenTelemetry implementation"
```

---

## Task 9: Update Documentation

**Files:**
- Modify: `README.md`

**Step 1: Add OpenTelemetry section**

```markdown
## OpenTelemetry

API supports OpenTelemetry for observability. To enable:

```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=http://your-collector:4318
export OTEL_SERVICE_NAME=scanme-php-api
```

Environment variables:
- `OTEL_EXPORTER_OTLP_ENDPOINT` - OTLP collector URL (required to enable)
- `OTEL_SERVICE_NAME` - Service name (default: scanme-php-api)
- `OTEL_TRACES_SAMPLER` - Sampler strategy (default: parentbased_always_on)
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add OpenTelemetry documentation"
```

---

## Summary

After completing all tasks:
- OpenTelemetry SDK is installed and configured
- HTTP requests are automatically traced via middleware
- QR generation has detailed manual instrumentation
- System is opt-in via environment variable
- Application works normally without OTel configured
