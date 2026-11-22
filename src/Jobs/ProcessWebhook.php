<?php

namespace Whilesmart\MetaMessaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Whilesmart\MetaMessaging\DeliveryStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\Events\MetaDeliveryUpdated;
use Whilesmart\MetaMessaging\Events\MetaMessageReceived;
use Whilesmart\MetaMessaging\Normalizers\InstagramNormalizer;
use Whilesmart\MetaMessaging\Normalizers\MessengerNormalizer;
use Whilesmart\MetaMessaging\Normalizers\WhatsAppNormalizer;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $body)
    {
        $this->onConnection(config('meta-messaging.queue_connection'));
        $this->onQueue(config('meta-messaging.queue_name'));
    }

    public function handle(): void
    {
        $payload = json_decode($this->body, true);
        $source = match ($payload['object'] ?? null) {
            'whatsapp_business_account' => [Platform::WhatsApp, new WhatsAppNormalizer],
            'instagram' => [Platform::Instagram, new InstagramNormalizer],
            'page' => [Platform::Messenger, new MessengerNormalizer],
            default => null,
        };
        if ($source === null) {
            return;
        }
        [$platform, $normalizer] = $source;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($normalizer->normalize($entry) as $external => $item) {
                $channel = config('meta-messaging.model')::query()->where('platform', $platform->value)
                    ->where('external_id', (string) $external)->where('status', 'connected')->first();
                if (! $channel) {
                    continue;
                }
                if ($item instanceof DeliveryStatus) {
                    event(new MetaDeliveryUpdated($channel, $item));

                    continue;
                }
                $identity = $item->messageId ?? json_encode($item->raw, JSON_THROW_ON_ERROR);
                $lock = Cache::lock('meta:message:'.$channel->id.':'.hash('sha256', $identity), 86400);
                if ($lock->get()) {
                    try {
                        event(new MetaMessageReceived($channel, $item));
                    } catch (Throwable $exception) {
                        $lock->release();
                        throw $exception;
                    }
                }
            }
        }
    }
}
