<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;
use Whilesmart\MetaMessaging\Http\Controllers\MetaChannelController;
use Whilesmart\MetaMessaging\Http\Requests\EmbeddedSignupRequest;
use Whilesmart\MetaMessaging\Http\Requests\FacebookConnectRequest;
use Whilesmart\MetaMessaging\Http\Requests\UpdateChannelRequest;
use Whilesmart\MetaMessaging\Http\Resources\MetaChannelResource;
use Whilesmart\MetaMessaging\Models\MetaChannel;
use Whilesmart\MetaMessaging\ResponseFormatters\DefaultResponseFormatter;

class CustomizationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        foreach ([
            'channels_table' => 'tenant_inboxes',
            'model' => TenantChannel::class,
            'resource' => TenantResource::class,
            'controller' => TenantController::class,
            'response_formatter' => TenantFormatter::class,
            'write_middleware' => [WriteHeader::class],
            'requests' => ['update' => TenantUpdate::class, 'embedded_signup' => TenantSignup::class, 'facebook_connect' => TenantFacebook::class],
            'resource_actions' => ['show', 'index', 'update'],
            'webhook_route_enabled' => false,
        ] as $key => $value) {
            $app['config']->set('meta-messaging.'.$key, $value);
        }
    }

    public function test_custom_requests_models_and_responses_on_package_routes(): void
    {
        $this->fakeConnections();
        $this->signup()->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->facebook('messenger')->assertUnprocessable()->assertJsonValidationErrors('consent');
        $id = $this->postJson('/api/meta-channels/whatsapp/embedded-signup', [
            'owner_type' => 'workspace', 'owner_id' => 1, 'code' => 'test_authorization_code', 'consent' => true,
            'phone_number_id' => 'test_phone_number_id', 'business_account_id' => 'test_waba_id',
        ])->assertCreated()->assertJsonPath('data.tenant_model', true)->assertHeader('X-Tenant-Write', 'yes')->json('data.id');
        $this->assertDatabaseHas('tenant_inboxes', ['id' => $id]);
        $this->getJson('/api/meta-channels/'.$id)->assertOk()->assertJsonPath('data.tenant_model', true)->assertHeader('X-Envelope', 'custom');
        $this->getJson('/api/meta-channels')->assertOk()->assertHeader('X-Controller', 'custom');
        $this->putJson('/api/meta-channels/'.$id, ['name' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->deleteJson('/api/meta-channels/'.$id)->assertStatus(405);
        $this->get('/meta/webhook')->assertNotFound();
    }
}

class TenantChannel extends MetaChannel {}

class TenantResource extends MetaChannelResource
{
    public function toArray($request): array
    {
        return parent::toArray($request) + ['tenant_model' => $this->resource instanceof TenantChannel];
    }
}

class TenantController extends MetaChannelController
{
    public function index(Request $request): JsonResponse
    {
        return parent::index($request)->header('X-Controller', 'custom');
    }
}

class TenantFormatter extends DefaultResponseFormatter
{
    public function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return parent::success($data, $message, $statusCode)->header('X-Envelope', 'custom');
    }
}

class TenantUpdate extends UpdateChannelRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'min:8']];
    }
}

class TenantSignup extends EmbeddedSignupRequest
{
    public function rules(): array
    {
        return parent::rules() + ['consent' => ['accepted']];
    }
}

class TenantFacebook extends FacebookConnectRequest
{
    public function rules(): array
    {
        return parent::rules() + ['consent' => ['accepted']];
    }
}

class WriteHeader
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request)->header('X-Tenant-Write', 'yes');
    }
}
