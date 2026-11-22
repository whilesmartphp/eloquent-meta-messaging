<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Whilesmart\MetaMessaging\MetaMessagingServiceProvider;
use Whilesmart\OwnerAccess\OwnerAccessServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/Fixtures/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function fakeConnections(): void
    {
        $data = $this->fixture('graph');
        Http::fake([
            '*/oauth/access_token*' => Http::response($data['token']),
            '*/phone_numbers*' => Http::response($data['phones']),
            '*/me/accounts*' => Http::response($data['pages']),
            '*/me?*' => Http::response($data['page_details']),
            '*/subscribed_apps' => Http::response($data['subscription']),
        ]);
    }

    protected function signup(int $owner = 1)
    {
        return $this->postJson('/api/meta-channels/whatsapp/embedded-signup', [
            'owner_type' => 'workspace', 'owner_id' => $owner, 'code' => 'test_authorization_code',
            'phone_number_id' => 'test_phone_number_id', 'business_account_id' => 'test_waba_id',
        ]);
    }

    protected function facebook(string $platform)
    {
        return $this->postJson('/api/meta-channels/facebook/connect', [
            'owner_type' => 'workspace', 'owner_id' => 1, 'code' => 'test_authorization_code',
            'redirect_uri' => 'https://example.org/callback', 'platform' => $platform, 'page_ids' => ['117172741761305'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            OwnerAccessServiceProvider::class,
            MetaMessagingServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('meta-messaging.route_middleware', ['api']);
        $app['config']->set('meta-messaging.app_secret', 'test-whatsapp-secret');
        $app['config']->set('meta-messaging.verify_token', 'ig-token');
        $app['config']->set('meta-messaging.queue_connection', 'sync');
        $app['config']->set('cache.default', 'array');
    }
}
