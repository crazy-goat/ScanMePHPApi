<?php
namespace App\Controller;

use CrazyGoat\ScanMePHP\QRCode;
use CrazyGoat\ScanMePHP\QRCodeConfig;
use CrazyGoat\ScanMePHP\Renderer\SvgRenderer;
use CrazyGoat\ScanMePHP\Renderer\PngRenderer;
use CrazyGoat\ScanMePHP\Renderer\HtmlDivRenderer;
use CrazyGoat\ScanMePHP\Renderer\HtmlTableRenderer;
use CrazyGoat\ScanMePHP\Renderer\FullBlocksRenderer;
use CrazyGoat\ScanMePHP\Renderer\HalfBlocksRenderer;
use CrazyGoat\ScanMePHP\Renderer\SimpleRenderer;
use support\Request;
use support\Response;

class QrController
{
    private const DEFAULT_FORMAT = 'svg';
    private const DEFAULT_ECC = 'M';
    private const DEFAULT_MARGIN = 0;

    public function __invoke(Request $request): Response
    {
        $data = $request->get('data');
        if (!$data) {
            return json(['error' => 'Missing data parameter'], 400);
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return json(['error' => 'Invalid base64 data'], 400);
        }

        if ($this->needsScheme($decoded)) {
            $decoded = 'http://' . $decoded;
        }

        $format = $request->get('format', self::DEFAULT_FORMAT);
        $mode = $request->get('mode', 'preview');
        $size = $request->get('size', 0);
        $ecc = $this->mapEcc($request->get('ecc', self::DEFAULT_ECC));
        $margin = (int) $request->get('margin', self::DEFAULT_MARGIN);
        $moduleStyle = $request->get('moduleStyle', 'square');
        $type = $request->get('type', '');
        $fg = '#' . ltrim($request->get('fg', '000000'), '#');
        $bg = '#' . ltrim($request->get('bg', 'FFFFFF'), '#');
        $label = $request->get('label', '');
        $invert = filter_var($request->get('invert', 'false'), FILTER_VALIDATE_BOOLEAN);

        $renderer = $this->createRenderer($format, $type, $margin);
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

        try {
            $qr = new QRCode($decoded, $config);
            $content = $qr->render();
        } catch (\CrazyGoat\ScanMePHP\Exception\InvalidDataException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Invalid URL') !== false) {
                return json(['error' => 'Invalid URL format. Make sure it starts with http:// or https://'], 400);
            }
            return json(['error' => 'Invalid data: ' . $msg], 400);
        } catch (\Throwable $e) {
            return json(['error' => 'Failed to generate QR code: ' . $e->getMessage()], 400);
        }
        $contentType = $this->getContentType($format);
        $filename = $this->getFilename($format);

        $headers = ['Content-Type' => $contentType];
        if ($mode === 'download') {
            $headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        }

        return new Response(200, $headers, $content);
    }

    private function createRenderer(string $format, string $type, int $margin): \CrazyGoat\ScanMePHP\RendererInterface
    {
        return match ($format) {
            'svg' => new \CrazyGoat\ScanMePHP\Renderer\SvgRenderer(moduleSize: 10),
            'png' => new \CrazyGoat\ScanMePHP\Renderer\PngRenderer(moduleSize: 10),
            'html' => match ($type) {
                'table' => new \CrazyGoat\ScanMePHP\Renderer\HtmlTableRenderer(moduleSize: 10, fullHtml: false),
                default => new \CrazyGoat\ScanMePHP\Renderer\HtmlDivRenderer(moduleSize: 10, fullHtml: false),
            },
            'ascii' => match ($type) {
                'half' => new \CrazyGoat\ScanMePHP\Renderer\HalfBlocksRenderer(sideMargin: $margin),
                'simple' => new \CrazyGoat\ScanMePHP\Renderer\SimpleRenderer(sideMargin: $margin),
                default => new \CrazyGoat\ScanMePHP\Renderer\FullBlocksRenderer(sideMargin: $margin),
            },
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
            'ascii' => 'text/plain; charset=utf-8',
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

    private function needsScheme(string $data): bool
    {
        if (empty($data)) {
            return false;
        }

        if (!filter_var($data, FILTER_VALIDATE_URL) && preg_match('/^[\w.-]+\.[a-zA-Z]{2,}$/', $data)) {
            return true;
        }

        return false;
    }
}
