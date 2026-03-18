<?php
namespace App\Controller;

use support\Request;
use support\Response;

class Index
{
    public function __invoke(Request $request): Response
    {
        $html = file_get_contents(__DIR__ . '/../view/index.html');
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
