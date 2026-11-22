<?php

namespace Whilesmart\MetaMessaging\Normalizers;

use Whilesmart\MetaMessaging\DeliveryStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\InboundMessage;

abstract class PageNormalizer extends Normalizer
{
    abstract protected function platform(): Platform;

    public function normalize(array $entry): iterable
    {
        $events = array_merge($entry['messaging'] ?? [], $entry['standby'] ?? []);
        foreach ($entry['changes'] ?? [] as $change) {
            if (isset($change['value'])) {
                $events[] = $change['value'];
            }
        }
        foreach ($events as $event) {
            $message = $event['message'] ?? $event['postback'] ?? [];
            $echo = $message['is_echo'] ?? false;
            $sender = $event['sender']['id'] ?? null;
            $channel = $echo ? $sender : ($event['recipient']['id'] ?? null);
            if (! $channel || ! $sender) {
                continue;
            }
            foreach (['read' => 'read', 'delivery' => 'delivered'] as $key => $status) {
                if (isset($event[$key])) {
                    $receipt = $event[$key];
                    $ids = $receipt['mids'] ?? [];
                    foreach ($ids ?: [$receipt['mid'] ?? null] as $id) {
                        yield $channel => new DeliveryStatus($id, $status, null,
                            $this->timestamp($receipt['watermark'] ?? $event['timestamp'] ?? null), $sender);
                    }
                }
            }
            if (! $message && ! isset($event['reaction'])) {
                continue;
            }
            $story = isset($message['reply_to']['story']) ? ['reply' => $message['reply_to']['story']] : null;
            $attachments = [];
            foreach ($message['attachments'] ?? [] as $attachment) {
                $type = $attachment['type'];
                $payload = $attachment['payload'] ?? [];
                $url = $payload['url'] ?? $payload['story_media_url'] ?? null;
                if ($type === 'sticker' && $url && in_array($url, array_column($attachments, 'url'), true)) {
                    continue;
                }
                $attachments[] = [
                    'type' => $type === 'sticker' ? 'image' : $type, 'url' => $url, 'mime' => null,
                    'id' => $payload['ig_post_media_id'] ?? null,
                ];
                if ($type === 'story_mention') {
                    $story = ['mention' => $payload];
                }
            }
            $reaction = $event['reaction'] ?? null;
            yield $channel => new InboundMessage($this->platform(), $channel, $sender, $event['sender']['name'] ?? null, $message['mid'] ?? null,
                $message['text'] ?? $message['title'] ?? $reaction['emoji'] ?? null, $attachments,
                $message['reply_to']['mid'] ?? $reaction['mid'] ?? null, $story, $echo,
                $this->timestamp($event['timestamp'] ?? null), $event, $reaction ? 'reaction' : ($attachments[0]['type'] ?? 'text'),
                $reaction ?? []);
        }
    }
}
