# ScanMePHP API Showcase - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a public API service showcasing ScanMePHP library with a modern landing page.

**Architecture:** Webman-based PHP API with single-file frontend view. Docker container deployed to GitHub Container Registry and Dokploy.

**Tech Stack:** Webman, ScanMePHP, Docker, Swagger UI

---

## Task 1: Scaffold Webman Project

**Files:**
- Create: `app/controller/Index.php`
- Create: `app/view/index.html`
- Create: `config/routes.php`
- Create: `public/index.php`
- Create: `composer.json`
- Create: `.env`

**Step 1: Create composer.json**

```json
{
    "name": "scanmephp/api",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "workerman/webman": "^1.5",
        "crazy-goat/scanmephp": "^0.4"
    },
    "require-dev": {
        "workerman/webman-test": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

**Step 2: Create public/index.php**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
```

**Step 3: Create config/routes.php**

```php
<?php
return [
    '' => \App\Controller\Index::class,
    'api/qr' => \App\Controller\QrController::class,
];
```

**Step 4: Create app/controller/Index.php**

```php
<?php
namespace App\Controller;

use Workerman\Http\Server;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class Index
{
    public function __invoke(Request $request): Response
    {
        $html = file_get_contents(__DIR__ . '/../view/index.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
```

**Step 5: Create app/view/index.html (scaffold only)**

```html
<!DOCTYPE html>
<html>
<head>
    <title>ScanMePHP API</title>
</head>
<body>
    <h1>ScanMePHP API</h1>
</body>
</html>
```

**Step 6: Run composer install**

Run: `cd /home/decodo/work/ScanMePHPApi && composer install`
Expected: Dependencies installed

**Step 7: Test Webman starts**

Run: `php start.php status` (or `php -S localhost:8787 public/index.php` for simple test)
Expected: Webman running

**Step 8: Commit**

```bash
git add -A && git commit -m "chore: scaffold webman project"
```

---

## Task 2: Create QrController

**Files:**
- Create: `app/controller/QrController.php`
- Test: Manual verification with curl

**Step 1: Create app/controller/QrController.php**

```php
<?php
namespace App\Controller;

use CrazyGoat\ScanMePHP\QRCode;
use CrazyGoat\ScanMePHP\QRCodeConfig;
use CrazyGoat\ScanMePHP\Renderer\SvgRenderer;
use CrazyGoat\ScanMePHP\Renderer\PngRenderer;
use CrazyGoat\ScanMePHP\Renderer\HtmlDivRenderer;
use CrazyGoat\ScanMePHP\Renderer\FullBlocksRenderer;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class QrController
{
    private const DEFAULT_FORMAT = 'svg';
    private const DEFAULT_ECC = 'M';
    private const DEFAULT_MARGIN = 4;

    public function __invoke(Request $request): Response
    {
        $data = $request->get('data');
        if (!$data) {
            return new Response(400, ['Content-Type' => 'application/json'], '{"error": "Missing data parameter"}');
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return new Response(400, ['Content-Type' => 'application/json'], '{"error": "Invalid base64 data"}');
        }

        $format = $request->get('format', self::DEFAULT_FORMAT);
        $mode = $request->get('mode', 'preview');
        $size = $request->get('size', 0);
        $ecc = $this->mapEcc($request->get('ecc', self::DEFAULT_ECC));
        $margin = (int) $request->get('margin', self::DEFAULT_MARGIN);
        $moduleStyle = $request->get('moduleStyle', 'square');
        $fg = $request->get('fg', '000000');
        $bg = $request->get('bg', 'FFFFFF');
        $label = $request->get('label', '');
        $invert = filter_var($request->get('invert', 'false'), FILTER_VALIDATE_BOOLEAN);

        $renderer = $this->createRenderer($format, $moduleStyle, $margin);
        $config = new QRCodeConfig(
            engine: $renderer,
            errorCorrectionLevel: $ecc,
            size: (int) $size ?: 0,
            margin: $margin,
            foregroundColor: $fg,
            backgroundColor: $bg,
            label: $label,
            invert: $invert
        );

        $qr = new QRCode($decoded, $config);

        $content = $qr->render();
        $contentType = $this->getContentType($format);
        $filename = $this->getFilename($format);

        $headers = ['Content-Type' => $contentType];
        if ($mode === 'download') {
            $headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        }

        return new Response(200, $headers, $content);
    }

    private function createRenderer(string $format, string $moduleStyle, int $margin): \CrazyGoat\ScanMePHP\RendererInterface
    {
        return match ($format) {
            'svg' => new \CrazyGoat\ScanMePHP\Renderer\SvgRenderer(moduleSize: 10),
            'png' => new \CrazyGoat\ScanMePHP\Renderer\PngRenderer(moduleSize: 10),
            'html' => new \CrazyGoat\ScanMePHP\Renderer\HtmlDivRenderer(moduleSize: 10, fullHtml: false),
            'ascii' => new \CrazyGoat\ScanMePHP\Renderer\FullBlocksRenderer(sideMargin: $margin),
            default => new \CrazyGoat\ScanMePHP\Renderer\SvgRenderer(moduleSize: 10),
        };
    }

    private function mapEcc(string $ecc): \CrazyGoat\ScanMePHP\ErrorCorrectionLevel
    {
        return match (strtoupper($ecc)) {
            'L' => \CrazyGoat\ScanMePHP\ErrorCorrectionLevel::Low,
            'Q' => \CrazyGoat\ScanMePHP\ErrorCorrectionLevel::Quartile,
            'H' => \CrazyGoat\ScanMePHP\ErrorCorrectionLevel::High,
            default => \CrazyGoat\ScanMePHP\ErrorCorrectionLevel::Medium,
        };
    }

    private function getContentType(string $format): string
    {
        return match ($format) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'html' => 'text/html',
            'ascii' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    private function getFilename(string $format): string
    {
        return match ($format) {
            'svg' => 'qr.svg',
            'png' => 'qr.png',
            'html' => 'qr.html',
            'ascii' => 'qr.txt',
            default => 'qr.bin',
        };
    }
}
```

**Step 2: Test with curl**

Run: `curl "http://localhost:8787/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ==&format=svg"`
Expected: SVG QR code output

Run: `curl "http://localhost:8787/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ==&format=svg&mode=download" -I`
Expected: Header contains `Content-Disposition: attachment; filename="qr.svg"`

**Step 3: Commit**

```bash
git add app/controller/QrController.php && git commit -m "feat: add QrController with all renderers"
```

---

## Task 3: Create Landing Page (index.html)

**Files:**
- Modify: `app/view/index.html`
- Test: Manual browser verification

**Step 1: Write complete index.html**

Full implementation with:
- Modern CSS (gradient header, cards, rounded buttons)
- URL input with placeholder "Enter URL or text..."
- Format buttons (SVG, PNG, HTML, ASCII)
- Sub-options that slide/fade in (HTML: Div/Table, ASCII: Full/Half/Simple)
- QR preview area
- Generate & Download button
- curl example section
- Link to /docs

Design specs:
- Color palette: Dark background (#0f172a), accent (#3b82f6), text (#f8fafc)
- System font stack: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif
- Responsive grid
- No external JS dependencies

**Step 2: Test in browser**

Open: `http://localhost:8787/`
Expected: Modern landing page renders
- Enter URL → click SVG → QR preview appears
- Change format → sub-options show/hide appropriately
- Download button triggers file download

**Step 3: Commit**

```bash
git add app/view/index.html && git commit -m "feat: add modern landing page frontend"
```

---

## Task 4: Add OpenAPI Specification

**Files:**
- Create: `config/openapi.yaml`
- Modify: `config/routes.php` (add /docs route)

**Step 1: Create config/openapi.yaml**

```yaml
openapi: 3.0.3
info:
  title: ScanMePHP API
  description: Free QR code generation API powered by ScanMePHP library
  version: 1.0.0
servers:
  - url: https://api.scanmephp.com
    description: Production server
paths:
  /api/qr:
    get:
      summary: Generate QR code
      description: Generates a QR code with specified parameters
      parameters:
        - name: data
          in: query
          required: true
          description: Base64 encoded data to encode
          schema:
            type: string
          example: "aHR0cHM6Ly5leGFtcGxlLmNvbQ=="
        - name: format
          in: query
          required: false
          schema:
            type: string
            enum: [svg, png, html, ascii]
            default: svg
          description: Output format
        - name: mode
          in: query
          required: false
          schema:
            type: string
            enum: [preview, download]
            default: preview
          description: "preview = inline, download = attachment"
        - name: size
          in: query
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 40
          description: QR version (1-40), auto if omitted
        - name: ecc
          in: query
          required: false
          schema:
            type: string
            enum: [L, M, Q, H]
            default: M
          description: Error correction level
        - name: margin
          in: query
          required: false
          schema:
            type: integer
            default: 4
          description: Quiet zone in modules
        - name: moduleStyle
          in: query
          required: false
          schema:
            type: string
            enum: [square, rounded, dot]
            default: square
          description: Module style (SVG only)
        - name: fg
          in: query
          required: false
          schema:
            type: string
            default: "000000"
          description: Foreground color hex
        - name: bg
          in: query
          required: false
          schema:
            type: string
            default: "FFFFFF"
          description: Background color hex
        - name: label
          in: query
          required: false
          schema:
            type: string
          description: Label below QR code
        - name: invert
          in: query
          required: false
          schema:
            type: boolean
            default: false
          description: Swap foreground/background
      responses:
        200:
          description: QR code image
          content:
            image/svg+xml:
              schema:
                type: string
                format: binary
            image/png:
              schema:
                type: string
                format: binary
            text/html:
              schema:
                type: string
            text/plain:
              schema:
                type: string
        400:
          description: Invalid parameters
```

**Step 2: Add /docs and /openapi.yaml routes**

Modify config/routes.php:
```php
return [
    '' => \App\Controller\Index::class,
    'api/qr' => \App\Controller\QrController::class,
    'openapi.yaml' => \App\Controller\OpenApiController::class,
    'docs' => \App\Controller\SwaggerController::class,
];
```

**Step 3: Create OpenApiController**

```php
<?php
namespace App\Controller;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class OpenApiController
{
    public function __invoke(Request $request): Response
    {
        $yaml = file_get_contents(__DIR__ . '/../../config/openapi.yaml');
        return new Response(200, ['Content-Type' => 'text/yaml'], $yaml);
    }
}
```

**Step 4: Create SwaggerController**

For `/docs` - either:
- Redirect to external Swagger UI CDN (simplest)
- Serve swagger-ui assets

Option: Use redirect to CDN for simplicity:
```php
return new Response(302, ['Location' => 'https://petstore.swagger.io/?url=https://api.scanmephp.com/openapi.yaml']);
```

Or serve embedded UI (more complex, requires assets).

**Step 5: Commit**

```bash
git add config/openapi.yaml app/controller/OpenApiController.php config/routes.php && git commit -m "feat: add OpenAPI spec and docs endpoint"
```

---

## Task 5: Create Dockerfile

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `.dockerignore`

**Step 1: Create .dockerignore**

```
vendor/
.idea/
*.log
.env
```

**Step 2: Create Dockerfile**

```dockerfile
FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

EXPOSE 8787

CMD ["php", "start.php", "start"]
```

**Step 3: Create docker-compose.yml**

```yaml
services:
  webman:
    build: .
    ports:
      - "8787:8787"
    environment:
      - APP_ENV=production
    restart: unless-stopped
```

**Step 4: Build and test locally**

Run: `docker compose build`
Expected: Image builds successfully

Run: `docker compose up -d`
Run: `curl "http://localhost:8787/api/qr?data=aHR0cHM6Ly5leGFtcGxlLmNvbQ=="`
Expected: QR code returns

**Step 5: Commit**

```bash
git add Dockerfile docker-compose.yml .dockerignore && git commit -m "chore: add Dockerfile and docker-compose"
```

---

## Task 6: Setup GitHub Container Registry

**Files:**
- Modify: `Dockerfile` (add ghcr.io metadata)
- Create: `.github/workflows/docker-publish.yml`

**Step 1: Create .github/workflows/docker-publish.yml**

```yaml
name: Build and Push Docker Image

on:
  push:
    branches: [main]
  tags:
    - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to GitHub Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ github.repository }}
          tags: |
            latest
            ${{ github.sha }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
```

**Step 2: Commit**

```bash
git add .github/workflows/docker-publish.yml && git commit -m "ci: add docker build and push workflow"
```

---

## Task 7: Create README

**Files:**
- Create: `README.md`

**Content:**
- Brief description
- API endpoint documentation
- curl examples
- Deployment instructions (Dokploy)
- Links to ScanMePHP library

**Commit**

```bash
git add README.md && git commit -m "docs: add README"
```

---

## Task 8: Initial Git Setup

**Step 1: Initialize git and push to GitHub**

Run: `git init`
Run: `gh repo create ScanMePHPApi --public --source=. --remote=origin` (or manual)
Run: `git branch -M main`
Run: `git push -u origin main`

---

## Summary

| Task | Description |
|------|-------------|
| 1 | Scaffold Webman project |
| 2 | Create QrController with all renderers |
| 3 | Create modern landing page (index.html) |
| 4 | Add OpenAPI spec + docs endpoint |
| 5 | Create Dockerfile |
| 6 | Setup GitHub Container Registry workflow |
| 7 | Create README |
| 8 | Git init and push |

**Total: 8 tasks**
