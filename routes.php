<?php

use AhmadFatoni\ApiGenerator\Classes\ApiRegistry;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;
use AhmadFatoni\ApiGenerator\Http\ApiController;
use AhmadFatoni\ApiGenerator\Http\ApiAuthMiddleware;
use AhmadFatoni\ApiGenerator\Http\AuthController;

/*
 * Authentication, available when RainLab.User is installed
 */
if (ApiGenerator::hasUserPlugin()) {
    Route::post('api/v1/auth/login', [AuthController::class, 'login']);
    Route::middleware([ApiAuthMiddleware::class])->group(function () {
        Route::post('api/v1/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('api/v1/auth/me', [AuthController::class, 'me']);
    });
}

/*
 * Generated APIs, registered from the saved API definitions. Plugin routes load while the
 * plugins register, before the cache and database are ready, so wait until the app has booted.
 */
App::booted(function () {
    foreach (ApiRegistry::all() as $api) {
        Route::middleware($api['middleware'])->group(function () use ($api) {
            $endpoint = $api['endpoint'];

            $action = function ($method, $name) use ($api, $endpoint) {
                return [
                    'uses' => ApiController::class . '@' . $method,
                    'as' => $endpoint . '.' . $name,
                    'apigenerator' => $api['id'],
                ];
            };

            Route::get($endpoint, $action('index', 'index'));
            Route::post($endpoint, $action('store', 'store'));
            Route::get($endpoint . '/{id}', $action('show', 'show'));
            Route::match(['PUT', 'PATCH'], $endpoint . '/{id}', $action('update', 'update'));
            Route::delete($endpoint . '/{id}', $action('destroy', 'destroy'));
            Route::get($endpoint . '/{id}/delete', $action('destroy', 'delete'));
        });
    }

    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
});
