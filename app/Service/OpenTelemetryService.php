<?php
namespace App\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryService
{
    private static ?self $instance = null;
    private ?TracerProvider $tracerProvider = null;
    private ?LoggerProvider $loggerProvider = null;
    private ?TracerInterface $tracer = null;
    private ?LoggerInterface $logger = null;
    private bool $enabled = false;
    private ResourceInfo $resource;

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
        $this->initializeResource($config);
        $this->initializeTracer($config);
        $this->initializeLogger($config);
    }

    private function initializeResource(array $config): void
    {
        $this->resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $config['service']['name'],
            ResourceAttributes::SERVICE_VERSION => $config['service']['version'],
            ResourceAttributes::SERVICE_NAMESPACE => $config['service']['namespace'] ?? null,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => getenv('APP_ENV') ?: 'production',
        ]));
    }

    private function initializeTracer(array $config): void
    {
        $endpoint = $config['exporter']['endpoint'];
        $transport = (new OtlpHttpTransportFactory())->create($endpoint . '/v1/traces', 'application/x-protobuf');
        $exporter = new SpanExporter($transport);

        $sampler = $this->createSampler($config['traces']['sampler']);

        $this->tracerProvider = new TracerProvider(
            [new SimpleSpanProcessor($exporter)],
            $sampler,
            $this->resource
        );

        $this->tracer = $this->tracerProvider->getTracer(
            $config['service']['name'],
            $config['service']['version']
        );
    }

    private function initializeLogger(array $config): void
    {
        if (!$config['logs']['enabled']) {
            return;
        }

        $endpoint = $config['exporter']['endpoint'];
        $transport = (new OtlpHttpTransportFactory())->create($endpoint . '/v1/logs', 'application/x-protobuf');
        $exporter = new LogsExporter($transport);

        $this->loggerProvider = LoggerProvider::builder()
            ->addLogRecordProcessor(new SimpleLogRecordProcessor($exporter))
            ->setResource($this->resource)
            ->build();

        $this->logger = $this->loggerProvider->getLogger($config['service']['name']);
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

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function shutdown(): void
    {
        if ($this->tracerProvider !== null) {
            $this->tracerProvider->shutdown();
        }
        if ($this->loggerProvider !== null) {
            $this->loggerProvider->shutdown();
        }
    }
}
