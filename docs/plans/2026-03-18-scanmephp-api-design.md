# ScanMePHP API Showcase - Design

## Overview

Public API service showcasing ScanMePHP library capabilities. Simple landing page for generating QR codes + full API documentation.

## Stack

- **Backend:** Webman (PHP)
- **Frontend:** Single `app/view/index.html` (embedded CSS/JS)
- **Container:** Docker → GitHub Container Registry
- **Deployment:** Dokploy on VPS

## Structure

```
ScanMePHPApi/
├── app/
│   ├── controller/
│   │   └── QrController.php
│   └── view/
│       └── index.html
├── config/
│   └── openapi.yaml
├── public/
│   └── .htaccess (rewrite to index)
├── Dockerfile
├── docker-compose.yml
└── README.md
```

## API Endpoints

| Method | Endpoint | Opis |
|--------|----------|------|
| `GET` | `/` | Landing page (index.html view) |
| `GET` | `/api/qr` | Generate QR code |
| `GET` | `/openapi.yaml` | OpenAPI spec file |
| `GET` | `/docs` | Swagger UI |

## GET /api/qr

### Query Parameters

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `data` | string (base64) | Yes | - | Data to encode |
| `format` | string | No | `svg` | `svg\|png\|html\|ascii` |
| `mode` | string | No | `preview` | `preview\|download` |
| `size` | int (1-40) | No | auto | QR version |
| `ecc` | string | No | `M` | Error correction: `L\|M\|Q\|H` |
| `margin` | int | No | `4` | Quiet zone in modules |
| `moduleStyle` | string | No | `square` | `square\|rounded\|dot` (SVG only) |
| `fg` | string | No | `000000` | Foreground color hex |
| `bg` | string | No | `FFFFFF` | Background color hex |
| `label` | string | No | - | Label below QR code |
| `invert` | bool | No | `false` | Swap foreground/background |

### Response

**format = svg:**
- `Content-Type: image/svg+xml`
- `mode=download` → `Content-Disposition: attachment; filename="qr.svg"`

**format = png:**
- `Content-Type: image/png`
- `mode=download` → `Content-Disposition: attachment; filename="qr.png"`

**format = html:**
- Returns HTML fragment with QR code
- Uses Div renderer by default (not configurable via API, keeps it simple)
- `Content-Type: text/html`
- `mode=download` → `Content-Disposition: attachment; filename="qr.html"`

**format = ascii:**
- `Content-Type: text/plain`
- `mode=download` → `Content-Disposition: attachment; filename="qr.txt"`

## Frontend (index.html)

### Layout

```
┌─────────────────────────────────────────────────┐
│  ScanMePHP API                    [API Docs]    │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │  Enter URL or text...                    │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐               │
│  │ SVG │ │ PNG │ │ HTML│ │ASCII│  ← buttons   │
│  └─────┘ └─────┘ └─────┘ └─────┘               │
│                                                 │
│  ┌─ HTML ─────────┐  ┌─ ASCII ─────────┐       │
│  │ ○ Div  ● Table │  │ ○ Full ○ Half ○ │       │  ← sub-options
│  └─────────────────┘  └─────────────────┘         │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │                                         │   │
│  │           [QR CODE PREVIEW]             │   │
│  │                                         │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│         [ Generate & Download ]                 │
│                                                 │
├─────────────────────────────────────────────────┤
│  Try it with curl:                              │
│  $ curl "http://api/qr?data=...&format=svg"    │
│                                                 │
│  [View Full API Documentation →]                │
└─────────────────────────────────────────────────┘
```

### Features

1. URL/text input field
2. Format buttons (SVG, PNG, HTML, ASCII)
3. Sub-options (show on format selection):
   - HTML: Div / Table radio
   - ASCII: Full / Half / Simple radio
4. Live preview of QR code (polling or on input change)
5. Generate & Download button
6. curl example below
7. Link to `/docs`

### Design

- Modern, minimal design
- System fonts
- Subtle gradients, rounded corners
- Responsive (mobile-friendly)
- No external dependencies (pure HTML/CSS/JS)

## OpenAPI

Manual YAML spec in `config/openapi.yaml`.

Full options documented (all params from table above).

Swagger UI served via `/docs` using `swagger-ui` assets or library.

## Docker

```dockerfile
FROM php:8.4-cli
# Install webman deps
# Copy app files
# Expose 8787
```

Image pushed to GitHub Container Registry.

## TODO

- [ ] Scaffold Webman project
- [ ] Create QrController
- [ ] Create index.html view
- [ ] Add OpenAPI spec
- [ ] Add Swagger UI
- [ ] Write Dockerfile
- [ ] Test locally
- [ ] Setup GitHub Container Registry
- [ ] Deploy to Dokploy
