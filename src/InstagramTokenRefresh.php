<?php

namespace Whilesmart\MetaMessaging;

use Illuminate\Support\Facades\DB;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class InstagramTokenRefresh
{
    public function __construct(private GraphClient $graph) {}

    public function token(MetaChannel $channel): string
    {
        $expiry = $channel->token_expires_at;
        if ($expiry && $expiry->lessThanOrEqualTo(now())) {
            throw new MetaApiException('Token expired. Reconnect this channel.', 190);
        }
        if ($channel->platform !== Platform::Instagram || $channel->business_account_id !== null || ! $expiry
            || $expiry->greaterThanOrEqualTo(now()->addDays(10)) || $channel->updated_at->greaterThan(now()->subDay())) {
            return $channel->access_token;
        }

        $expected = $channel->access_token;
        $data = $this->graph->request('GET', 'refresh_access_token', null, [
            'grant_type' => 'ig_refresh_token', 'access_token' => $expected,
        ], instagram: true);
        if (empty($data['access_token']) || ($data['expires_in'] ?? 0) <= 0) {
            throw new MetaApiException('Invalid token refresh response.');
        }

        return DB::transaction(function () use ($channel, $expected, $data) {
            $current = $channel->newQuery()->lockForUpdate()->findOrFail($channel->id);
            if ($current->business_account_id === null && $current->access_token === $expected) {
                $current->update(['access_token' => $data['access_token'], 'token_expires_at' => now()->addSeconds($data['expires_in'])]);
            }
            $channel->refresh();

            return $current->access_token;
        });
    }
}
