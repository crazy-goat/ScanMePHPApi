<?php
namespace App\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryService
{
    private static ?self $instance = null;
    private ?TracerProvider $tracerProvider = null;
    private ?LoggerProvider $loggerProvider = null;
    private ?MeterProvider $meterProvider = null;
    private ?ExportingReader $metricReader = null;
    private ?TracerInterface $tracer = null;
    private ?LoggerInterface $logger = null;
    private ?MeterInterface $meter = null;
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
        $this->initializeMetrics($config);
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

        $processor = (new BatchSpanProcessorBuilder($exporter))->build();

        $this->tracerProvider = new TracerProvider(
            [$processor],
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

    private function initializeMetrics(array $config): void
    {
        if (!$config['metrics']['enabled']) {
            return;
        }

        $endpoint = $config['exporter']['endpoint'];
        $transport = (new OtlpHttpTransportFactory())->create($endpoint . '/v1/metrics', 'application/x-protobuf');
        $exporter = new MetricExporter($transport);
        $this->metricReader = new ExportingReader($exporter);

        $this->meterProvider = MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($this->metricReader)
            ->build();

        $this->meter = $this->meterProvider->getMeter($config['service']['name']);
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

    public function getMeter(): ?MeterInterface
    {
        return $this->meter;
    }

    public function getMetricReader(): ?ExportingReader
    {
        return $this->metricReader;
    }

    public function shutdown(): void
    {
        if ($this->tracerProvider !== null) {
            $this->tracerProvider->shutdown();
        }
        if ($this->loggerProvider !== null) {
            $this->loggerProvider->shutdown();
        }
        if ($this->meterProvider !== null) {
            $this->meterProvider->shutdown();
        }
    }
}
