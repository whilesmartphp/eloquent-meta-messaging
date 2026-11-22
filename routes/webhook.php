<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\MetaMessaging\Http\Controllers\WebhookController;

Route::get(config('meta-messaging.webhook_path'), [WebhookController::class, 'verify']);
Route::post(config('meta-messaging.webhook_path'), [WebhookController::class, 'receive']);
