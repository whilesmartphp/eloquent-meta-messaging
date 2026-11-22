<?php

use Illuminate\Support\Facades\Route;

$controller = config('meta-messaging.controller');
$actions = config('meta-messaging.resource_actions');
Route::apiResource('meta-channels', $controller)->only(array_intersect($actions, ['index', 'show']));
Route::middleware(config('meta-messaging.write_middleware'))->group(function () use ($controller, $actions) {
    Route::apiResource('meta-channels', $controller)->only(array_intersect($actions, ['update', 'destroy']));
    if (config('meta-messaging.connection_routes_enabled')) {
        Route::post('meta-channels/whatsapp/embedded-signup', [$controller, 'embeddedSignup']);
        Route::post('meta-channels/facebook/connect', [$controller, 'facebookConnect']);
    }
});
