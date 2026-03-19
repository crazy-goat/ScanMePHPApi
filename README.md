# ScanMePHP API

Free QR code generation API powered by [ScanMePHP](https://github.com/crazy-goat/ScanMePHP) library.

## Live Demo

Visit [https://api.scanmephp.com](https://api.scanmephp.com) to try the interactive demo.

## Quick Start

### Generate QR Code

```bash
# SVG format
curl "https://api.scanmephp.com/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ==&format=svg"

# PNG format
curl "https://api.scanmephp.com/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ==&format=png" -o qr.png

# Download as file
curl "https://api.scanmephp.com/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ==&format=svg&mode=download" -o qr.svg
```

### API Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `data` | string (base64) | required | Data to encode |
| `format` | string | `svg` | `svg`, `png`, `html`, `ascii` |
| `mode` | string | `preview` | `preview` (inline) or `download` (attachment) |
| `size` | int (1-40) | auto | QR version |
| `ecc` | string | `M` | Error correction: `L`, `M`, `Q`, `H` |
| `margin` | int | `4` | Quiet zone in modules |
| `moduleStyle` | string | `square` | `square`, `rounded`, `dot` (SVG only) |
| `fg` | string | `000000` | Foreground color hex |
| `bg` | string | `FFFFFF` | Background color hex |
| `label` | string | - | Label below QR code |
| `invert` | bool | `false` | Swap foreground/background |

### Example with Parameters

```bash
# High error correction, custom colors
curl "https://api.scanmephp.com/api/qr?data=dGVzdA==&format=svg&ecc=H&fg=ff0000&bg=000000"
```

## API Documentation

Full OpenAPI specification available at:
- [OpenAPI YAML](https://api.scanmephp.com/openapi.yaml)
- [Swagger UI](https://api.scanmephp.com/docs)

## OpenTelemetry

API supports OpenTelemetry for observability. To enable:

```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=http://your-collector:4318
export OTEL_SERVICE_NAME=scanme-php-api
```

### SigNoz Integration

For SigNoz observability platform:

```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
export OTEL_SERVICE_NAME=ScanMePHPApi
```

Or use the provided docker-compose:
```bash
docker-compose -f docker-compose.signoz.yml up -d
```

### Environment Variables

- `OTEL_EXPORTER_OTLP_ENDPOINT` - OTLP collector URL (required to enable)
- `OTEL_SERVICE_NAME` - Service name (default: scanme-php-api)
- `OTEL_TRACES_SAMPLER` - Sampler strategy (default: parentbased_always_on)

## Docker

```bash
# Pull from GitHub Container Registry
docker pull ghcr.io/crazy-goat/scanmephpapi:latest

# Run
docker run -d -p 8787:8787 ghcr.io/crazy-goat/scanmephpapi:latest

# Or build locally
docker compose up -d
```

## Development

```bash
# Install dependencies
composer install

# Run locally
./run.sh start

# Or without extension wrapper
php start.php start
```

## Tech Stack

- [Webman](https://www.workerman.net/) - High-performance PHP framework
- [ScanMePHP](https://github.com/crazy-goat/ScanMePHP) - Pure PHP QR code generator
- [Swagger UI](https://swagger.io/) - API documentation

## License

MIT
