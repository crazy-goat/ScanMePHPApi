<?php
namespace App\Controller;

use support\Request;
use support\Response;

class HealthController
{
    public function live(Request $request): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ]));
    }

    public function ready(Request $request): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ]));
    }

    public function health(Request $request): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ]));
    }
}
