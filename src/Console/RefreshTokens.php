<?php

namespace Whilesmart\MetaMessaging\Console;

use Illuminate\Console\Command;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;
use Whilesmart\MetaMessaging\InstagramTokenRefresh;

class RefreshTokens extends Command
{
    protected $signature = 'meta-messaging:refresh-tokens';

    protected $description = 'Refresh Instagram Login tokens approaching expiry';

    public function handle(InstagramTokenRefresh $refresh): int
    {
        $result = self::SUCCESS;
        $channels = config('meta-messaging.model')::query()->where('platform', 'instagram')
            ->where('status', 'connected')->whereNull('business_account_id')
            ->where('token_expires_at', '<', now()->addDays(10))->lazyById();
        foreach ($channels as $channel) {
            try {
                $refresh->token($channel);
            } catch (MetaApiException $exception) {
                $this->error("Channel {$channel->id} could not refresh (code {$exception->getCode()}).");
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
