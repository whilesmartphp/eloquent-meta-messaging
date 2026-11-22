<?php

namespace Whilesmart\MetaMessaging;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Whilesmart\MetaMessaging\Enums\ChannelStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class ChannelConnections
{
    public function __construct(private GraphClient $graph) {}

    public function whatsapp(array $input): MetaChannel
    {
        $token = $this->exchange($input);
        $business = $input['business_account_id'];
        foreach ($this->graph->records($business.'/phone_numbers', $token) as $phone) {
            if ($phone['id'] === $input['phone_number_id']) {
                return $this->connect($input, Platform::WhatsApp, $phone['id'], $business,
                    $phone['verified_name'] ?? $phone['display_phone_number'], $token);
            }
        }

        throw ValidationException::withMessages(['phone_number_id' => 'This number is unavailable on the selected business account.']);
    }

    public function facebook(array $input): array
    {
        $platform = Platform::from($input['platform']);
        $token = $this->exchange($input);
        $pages = [];
        foreach ($this->graph->records('me/accounts', $token) as $page) {
            if (in_array($page['id'], $input['page_ids'], true)) {
                $pages[$page['id']] = $page;
            }
        }
        if (count($pages) !== count($input['page_ids'])) {
            throw ValidationException::withMessages(['page_ids' => 'A selected Page is unavailable.']);
        }

        $channels = [];
        foreach ($pages as $page) {
            $external = $page['id'];
            if ($platform === Platform::Instagram) {
                $details = $this->graph->request('GET', 'me', $page['access_token'], ['fields' => 'name,instagram_business_account']);
                $external = $details['instagram_business_account']['id'] ?? throw ValidationException::withMessages([
                    'page_ids' => 'A selected Page has no linked Instagram business account.',
                ]);
            }
            $channels[] = $this->connect($input, $platform, $external, $page['id'], $page['name'], $page['access_token']);
        }

        return $channels;
    }

    private function exchange(array $input): string
    {
        $response = $this->graph->request('GET', 'oauth/access_token', null, [
            'client_id' => config('meta-messaging.app_id'),
            'client_secret' => config('meta-messaging.app_secret'),
        ] + Arr::only($input, ['code', 'redirect_uri']));

        return $response['access_token'] ?? throw new MetaApiException('Meta returned no access token.');
    }

    private function connect(array $input, Platform $platform, string $external, string $subscription, string $name, string $token): MetaChannel
    {
        return $this->synchronized($platform, $subscription, function () use ($input, $platform, $external, $subscription, $name, $token) {
            $channel = config('meta-messaging.model')::withTrashed()->lockForUpdate()->firstOrNew([
                'platform' => $platform->value, 'external_id' => $external,
            ]);
            abort_if($channel->exists && ($channel->owner_type !== $input['owner_type']
                || (string) $channel->owner_id !== (string) $input['owner_id']), 409, 'Channel already assigned.');

            $channel->fill(Arr::only($input, ['owner_id', 'owner_type']) + [
                'name' => $name, 'access_token' => $token, 'token_expires_at' => null,
                'business_account_id' => $platform === Platform::Messenger ? null : $subscription,
                'status' => ChannelStatus::Connected,
            ]);
            $channel->save();
            if ($channel->trashed()) {
                $channel->restore();
            }

            $fields = $platform === Platform::WhatsApp
                ? ['messages', 'smb_message_echoes']
                : ['messages', 'message_deliveries', 'message_echoes', 'message_reads', 'message_reactions', 'standby', 'messaging_handovers', 'messaging_postbacks'];
            $this->graph->subscription($subscription, $token, $fields);

            return $channel;
        });
    }

    public function disconnect(MetaChannel $channel): void
    {
        $subscription = $channel->business_account_id ?? $channel->external_id;
        $this->synchronized($channel->platform, $subscription, function () use ($channel, $subscription) {
            $channel = $channel->newQuery()->lockForUpdate()->findOrFail($channel->getKey());
            $siblings = $channel->newQuery()->where('id', '!=', $channel->id);
            $directInstagram = $channel->platform === Platform::Instagram && $channel->business_account_id === null;
            if ($channel->platform === Platform::WhatsApp) {
                $siblings->where('platform', 'whatsapp')->where('business_account_id', $subscription);
            } else {
                $siblings->where(function ($query) use ($subscription) {
                    $query->where(fn ($q) => $q->where('platform', 'messenger')->where('external_id', $subscription))
                        ->orWhere(fn ($q) => $q->where('platform', 'instagram')->where('business_account_id', $subscription));
                });
            }
            if ($directInstagram || ! $siblings->exists()) {
                $this->graph->subscription($subscription, $channel->access_token, null, $directInstagram);
            }
            $channel->update(['status' => ChannelStatus::Disconnected]);
            $channel->delete();
        });
    }

    private function synchronized(Platform $platform, string $subscription, callable $action): mixed
    {
        $group = $platform === Platform::WhatsApp ? 'waba' : 'page';

        return Cache::lock('meta:subscription:'.$group.':'.$subscription, 120)
            ->block(10, fn () => DB::transaction($action));
    }
}
