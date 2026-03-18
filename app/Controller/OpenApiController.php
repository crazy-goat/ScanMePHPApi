<?php
namespace App\Controller;

use support\Request;
use support\Response;

class OpenApiController
{
    public function __invoke(Request $request): Response
    {
        $yaml = file_get_contents(__DIR__ . '/../../config/openapi.yaml');
        return new Response(200, ['Content-Type' => 'text/plain'], $yaml);
    }
}
