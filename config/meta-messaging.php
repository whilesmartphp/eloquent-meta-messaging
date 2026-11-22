<?php

use Whilesmart\MetaMessaging\Http\Controllers\MetaChannelController;
use Whilesmart\MetaMessaging\Http\Requests\EmbeddedSignupRequest;
use Whilesmart\MetaMessaging\Http\Requests\FacebookConnectRequest;
use Whilesmart\MetaMessaging\Http\Requests\UpdateChannelRequest;
use Whilesmart\MetaMessaging\Http\Resources\MetaChannelResource;
use Whilesmart\MetaMessaging\Models\MetaChannel;
use Whilesmart\MetaMessaging\ResponseFormatters\DefaultResponseFormatter;

return [
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    'verify_token' => env('META_VERIFY_TOKEN'),
    'graph_base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
    'instagram_base_url' => env('META_INSTAGRAM_BASE_URL', 'https://graph.instagram.com'),
    'graph_version' => env('META_GRAPH_VERSION', 'v22.0'),
    'whatsapp_configuration_id' => env('META_WHATSAPP_CONFIGURATION_ID'),
    'human_agent_tag' => env('META_HUMAN_AGENT_TAG', false),
    'channels_table' => env('META_CHANNELS_TABLE', 'meta_channels'),
    'register_routes' => env('META_REGISTER_ROUTES', true),
    'route_prefix' => env('META_ROUTE_PREFIX', 'api'),
    'route_middleware' => ['api', 'auth:sanctum'],
    'write_middleware' => [],
    'resource_actions' => ['index', 'show', 'update', 'destroy'],
    'connection_routes_enabled' => true,
    'webhook_route_enabled' => env('META_WEBHOOK_ROUTE_ENABLED', true),
    'webhook_path' => env('META_WEBHOOK_PATH', 'meta/webhook'),
    'webhook_middleware' => ['api'],
    'queue_connection' => env('META_QUEUE_CONNECTION'),
    'queue_name' => env('META_QUEUE_NAME'),
    'model' => MetaChannel::class,
    'controller' => MetaChannelController::class,
    'resource' => MetaChannelResource::class,
    'response_formatter' => DefaultResponseFormatter::class,
    'requests' => [
        'embedded_signup' => EmbeddedSignupRequest::class,
        'facebook_connect' => FacebookConnectRequest::class,
        'update' => UpdateChannelRequest::class,
    ],
];
