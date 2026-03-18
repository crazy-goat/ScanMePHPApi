<?php
namespace App\Controller;

use support\Request;
use support\Response;

class SwaggerUIController
{
    public function __invoke(Request $request): Response
    {
        $specUrl = 'https://api.scanmephp.com/openapi.yaml';
        return new Response(302, ['Location' => "https://petstore.swagger.io/?url={$specUrl}"]);
    }
}
