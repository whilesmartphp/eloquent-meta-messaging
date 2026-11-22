<?php

namespace Whilesmart\MetaMessaging\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Whilesmart\MetaMessaging\Jobs\ProcessWebhook;

class WebhookController
{
    public function verify(Request $request): Response
    {
        $token = config('meta-messaging.verify_token');
        abort_unless(is_string($token) && $token !== '' && $request->query('hub_mode') === 'subscribe'
            && is_string($request->query('hub_verify_token')) && hash_equals($token, $request->query('hub_verify_token'))
            && is_string($request->query('hub_challenge')), 403);

        return response($request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): Response
    {
        $secret = config('meta-messaging.app_secret');
        $body = $request->getContent();
        abort_unless(is_string($secret) && $secret !== '' && hash_equals('sha256='.hash_hmac('sha256', $body, $secret),
            (string) $request->header('X-Hub-Signature-256')), 401);
        ProcessWebhook::dispatch($body);

        return response('', 200);
    }
}
