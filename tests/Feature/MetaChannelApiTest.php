<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class MetaChannelApiTest extends TestCase
{
    public function test_signup_rename_disconnect_and_reconnect(): void
    {
        $this->fakeConnections();
        $response = $this->signup()->assertCreated()->assertJsonPath('data.name', 'Test Business')
            ->assertJsonMissingPath('data.access_token')->assertDontSee('new_access_token');
        $id = $response->json('data.id');
        $path = '/api/meta-channels/'.$id;
        $this->assertNotSame('new_access_token', DB::table('meta_channels')->value('access_token'));
        $this->assertSame('new_access_token', MetaChannel::findOrFail($id)->access_token);
        $this->getJson('/api/meta-channels')->assertJsonPath('data.meta.total', 1)->assertDontSee('new_access_token');
        $this->patchJson($path, ['name' => 'Support', 'owner_id' => 9])->assertOk()->assertJsonPath('data.owner_id', 1);
        $this->getJson($path)->assertOk()->assertJsonPath('data.name', 'Support')->assertJsonMissingPath('data.access_token');
        $this->deleteJson($path)->assertOk();
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/test_waba_id/subscribed_apps'));
        $this->getJson($path)->assertNotFound();
        $this->signup(2)->assertConflict();
        $this->signup()->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('meta_channels', 1);
    }

    public function test_failed_subscription_changes_leave_the_channel_in_its_previous_state(): void
    {
        $data = $this->fixture('graph');
        Http::fake([
            '*/oauth/access_token*' => Http::response($data['token']),
            '*/phone_numbers' => Http::response($data['phones']),
            '*/subscribed_apps' => Http::sequence()->push($data['error'], 400)
                ->push($data['subscription'])->push($data['error'], 400),
        ]);
        $this->signup()->assertStatus(502);
        $this->assertDatabaseCount('meta_channels', 0);
        $id = $this->signup()->assertCreated()->json('data.id');
        $this->deleteJson('/api/meta-channels/'.$id)->assertStatus(502);
        $this->getJson('/api/meta-channels/'.$id)->assertOk()->assertJsonPath('data.status', 'connected');
    }

    public function test_page_and_linked_instagram_share_one_subscription(): void
    {
        $this->fakeConnections();
        $page = $this->facebook('messenger')->assertCreated()->assertJsonPath('data.0.external_id', '117172741761305')
            ->assertDontSee('test-page-token')->json('data.0.id');
        $instagram = $this->facebook('instagram')->assertCreated()->assertJsonPath('data.0.external_id', 'instagram-business-id')
            ->assertJsonPath('data.0.business_account_id', '117172741761305')->assertDontSee('test-page-token')->json('data.0.id');
        $this->deleteJson('/api/meta-channels/'.$page)->assertOk();
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
        $this->deleteJson('/api/meta-channels/'.$instagram)->assertOk();
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/117172741761305/subscribed_apps'));
    }
}
