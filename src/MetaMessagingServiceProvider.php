<?php

namespace Whilesmart\MetaMessaging;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Whilesmart\MetaMessaging\Console\RefreshTokens;
use Whilesmart\MetaMessaging\Contracts\ResponseFormatter;
use Whilesmart\MetaMessaging\Http\Controllers\MetaChannelController;
use Whilesmart\MetaMessaging\Http\Requests\EmbeddedSignupRequest;
use Whilesmart\MetaMessaging\Http\Requests\FacebookConnectRequest;
use Whilesmart\MetaMessaging\Http\Requests\UpdateChannelRequest;
use Whilesmart\MetaMessaging\Http\Resources\MetaChannelResource;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class MetaMessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/meta-messaging.php', 'meta-messaging');
        $this->app->bindIf(ResponseFormatter::class, fn ($app) => $app->make(config('meta-messaging.response_formatter')));
    }

    public function boot(): void
    {
        foreach ([
            'model' => MetaChannel::class,
            'controller' => MetaChannelController::class,
            'resource' => MetaChannelResource::class,
            'response_formatter' => ResponseFormatter::class,
            'requests.update' => UpdateChannelRequest::class,
            'requests.embedded_signup' => EmbeddedSignupRequest::class,
            'requests.facebook_connect' => FacebookConnectRequest::class,
        ] as $key => $base) {
            $class = config('meta-messaging.'.$key, $base);
            if (! is_a($class, $base, true)) {
                throw new InvalidArgumentException("Invalid meta-messaging.$key class.");
            }
            if (str_starts_with($key, 'requests.') && $class !== $base) {
                $this->app->bind($base, $class);
            }
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/meta-messaging.php' => config_path('meta-messaging.php')], 'meta-messaging-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'meta-messaging-migrations');
        $this->commands([RefreshTokens::class]);
        Route::model('meta_channel', config('meta-messaging.model'));
        if (! $this->app->routesAreCached()) {
            if (config('meta-messaging.register_routes')) {
                Route::middleware(config('meta-messaging.route_middleware'))->prefix(config('meta-messaging.route_prefix'))
                    ->group(__DIR__.'/../routes/api.php');
            }
            if (config('meta-messaging.webhook_route_enabled')) {
                Route::middleware(config('meta-messaging.webhook_middleware'))->group(__DIR__.'/../routes/webhook.php');
            }
        }
    }
}
