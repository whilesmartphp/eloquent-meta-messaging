<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class RefreshTokensTest extends TestCase
{
    public function test_command_refreshes_only_eligible_instagram_login_tokens(): void
    {
        $this->freezeTime();
        $attributes = ['platform' => 'instagram', 'token_expires_at' => now()->addDay(), 'updated_at' => now()->subDays(2)];
        $eligible = MetaChannel::factory()->create($attributes);
        $page = MetaChannel::factory()->create(['business_account_id' => '123'] + $attributes);
        $young = MetaChannel::factory()->create(['updated_at' => now()] + $attributes);
        Http::fake(['*/refresh_access_token*' => Http::response($this->fixture('graph')['refresh'])]);
        $this->artisan('meta-messaging:refresh-tokens')->assertSuccessful();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_starts_with($r->url(), 'https://graph.instagram.com/refresh_access_token?')
            && str_contains($r->url(), 'grant_type=ig_refresh_token'));
        $this->assertSame('new_refreshed_token', $eligible->fresh()->access_token);
        $this->assertTrue($eligible->fresh()->token_expires_at->equalTo(now()->addDays(60)->startOfSecond()));
        $this->assertSame($page->access_token, $page->fresh()->access_token);
        $this->assertSame($young->access_token, $young->fresh()->access_token);
    }

    public function test_expired_token_requires_reconnection(): void
    {
        MetaChannel::factory()->create(['platform' => 'instagram', 'token_expires_at' => now()->subMinute()]);
        $this->artisan('meta-messaging:refresh-tokens')->assertFailed();
        Http::assertNothingSent();
    }
}
