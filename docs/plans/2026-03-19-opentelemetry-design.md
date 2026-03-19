# OpenTelemetry Design - ScanMePHP API

**Data:** 2026-03-19  
**Autor:** OpenCode Agent  
**Status:** Zatwierdzony

## Podsumowanie

Implementacja OpenTelemetry dla API Webman generującego kody QR. System ma działać opcjonalnie - aktywuje się tylko gdy skonfigurowany jest endpoint OTLP.

## Wymagania

- **Sygnały:** Traces + Metrics + Logs
- **Eksport:** OTLP (użytkownik podaje endpoint)
- **Instrumentacja:** Pełna (auto + manualne spany)
- **Tryb pracy:** Opcjonalny - działa lokalnie bez OTel, aktywuje się gdy podany endpoint

## Architektura

### Komponenty

1. **OTel SDK** (`open-telemetry/sdk`)
2. **OTel Exporter** (`open-telemetry/exporter-otlp`)
3. **Middleware** (`OpenTelemetryMiddleware`) - root span dla requestów
4. **Service** (`OpenTelemetryService`) - inicjalizacja SDK
5. **Manual spans** - w QrController dla szczegółów QR

### Struktura

```
app/
├── Middleware/
│   └── OpenTelemetryMiddleware.php
├── Service/
│   └── OpenTelemetryService.php
config/
├── opentelemetry.php
├── middleware.php (update)
```

## Sygnały Telemetryczne

### Traces

**Root Span** (Middleware):
- `http.method`, `http.url`, `http.status_code`
- `http.request.size`, `http.response.size`

**Child Span** (QrController):
- `qr.format` - format wyjściowy (svg, png, html, ascii)
- `qr.size` - rozmiar kodu
- `qr.ecc` - poziom korekcji błędów
- `qr.margin` - margines
- `qr.module_style` - styl modułów
- Events: `qr.render_start`, `qr.render_complete`, `qr.error`

### Metrics

- `qr.requests.total` (Counter) - liczba requestów z labelkami: format, status
- `qr.generation.duration` (Histogram) - czas generowania w ms
- `qr.errors.total` (Counter) - liczba błędów z labelką: error_type

### Logs

- Integracja z systemem logowania Webman
- Trace/Span ID w logach dla korelacji

## Konfiguracja

```php
// config/opentelemetry.php
return [
    'enabled' => !empty(env('OTEL_EXPORTER_OTLP_ENDPOINT')),
    'service' => [
        'name' => env('OTEL_SERVICE_NAME', 'scanme-php-api'),
        'version' => '1.0.0',
    ],
    'exporter' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
    ],
    'traces' => [
        'enabled' => true,
        'sampler' => env('OTEL_TRACES_SAMPLER', 'parentbased_always_on'),
    ],
    'metrics' => [
        'enabled' => true,
    ],
    'logs' => [
        'enabled' => true,
    ],
];
```

## Zmienne Środowiskowe

| Zmienna | Opis | Domyślnie |
|---------|------|-----------|
| `OTEL_EXPORTER_OTLP_ENDPOINT` | URL kolektora OTLP | - (brak = OTel wyłączony) |
| `OTEL_SERVICE_NAME` | Nazwa serwisu | scanme-php-api |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | Protokół OTLP | http/protobuf |
| `OTEL_TRACES_SAMPLER` | Sampler | parentbased_always_on |

## Flow Aktywacji

```
Start aplikacji
    ↓
OTEL_EXPORTER_OTLP_ENDPOINT ustawiony?
    ↓ TAK                          ↓ NIE
Inicjalizuj SDK                  Pomiń OTel
    ↓                                ↓
Rejestruj middleware             Aplikacja działa
    ↓                            bez OTel
Middleware tworzy root span
    ↓
QrController dodaje child span
    ↓
Export do OTLP
```

## Zależności Composer

```json
{
    "open-telemetry/sdk": "^1.0",
    "open-telemetry/exporter-otlp": "^1.0",
    "open-telemetry/opentelemetry-auto-psr15": "^0.0.1"
}
```

## Breaking Changes

- Brak (system opcjonalny)

## Testowanie

1. Lokalnie bez endpointu - aplikacja działa normalnie
2. Z lokalnym Jaeger/Prometheus via docker-compose
3. Weryfikacja obecności trace_id w logach
