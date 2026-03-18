<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

Route::get('/', [\App\Controller\Index::class, '__invoke']);

Route::any('/api/qr', [\App\Controller\QrController::class, '__invoke']);

Route::any('/openapi.yaml', [\App\Controller\OpenApiController::class, '__invoke']);

Route::any('/docs', [\App\Controller\SwaggerUIController::class, '__invoke']);

// Serve fonts statically
Route::get('/fonts/{file}', function ($request, $file) {
    $path = public_path() . '/fonts/' . $file;
    if (file_exists($path)) {
        return response()->file($path);
    }
    return response('Not found', 404);
});

