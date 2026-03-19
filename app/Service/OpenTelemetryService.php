<?php
namespace App\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
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
        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $config['service']['name'],
            ResourceAttributes::SERVICE_VERSION => $config['service']['version'],
            ResourceAttributes::SERVICE_NAMESPACE => $config['service']['namespace'] ?? null,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => getenv('APP_ENV') ?: 'production',
        ]));

        $endpoint = $config['exporter']['endpoint'];
        $transport = (new OtlpHttpTransportFactory())->create($endpoint . '/v1/traces', 'application/x-protobuf');
        $exporter = new SpanExporter($transport);

        $sampler = $this->createSampler($config['traces']['sampler']);

        $this->tracerProvider = new TracerProvider(
            [new SimpleSpanProcessor($exporter)],
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
            'always_off' => new AlwaysOffSampler(),
            'traceidratio' => new TraceIdRatioBasedSampler(0.5),
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
