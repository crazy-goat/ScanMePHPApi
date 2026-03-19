<?php
/**
 * Simple health check script for Docker
 * Returns exit code 0 if healthy, 1 if unhealthy
 */

$host = 'localhost';
$port = 8787;
$path = '/status/live';

// Try to connect
$socket = @fsockopen($host, $port, $errno, $errstr, 3);

if (!$socket) {
    echo "Health check failed: $errstr ($errno)\n";
    exit(1);
}

// Send HTTP request
$request = "GET $path HTTP/1.1\r\n";
$request .= "Host: $host:$port\r\n";
$request .= "Connection: close\r\n\r\n";

fwrite($socket, $request);

// Read response
$response = '';
while (!feof($socket)) {
    $response .= fgets($socket, 128);
}

fclose($socket);

// Check if response contains 200 OK
if (strpos($response, 'HTTP/1.1 200') !== false || strpos($response, 'HTTP/1.0 200') !== false) {
    echo "Health check passed\n";
    exit(0);
}

echo "Health check failed: unexpected response\n";
exit(1);
