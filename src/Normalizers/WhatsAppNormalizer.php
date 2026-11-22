<?php

namespace Whilesmart\MetaMessaging\Normalizers;

use Whilesmart\MetaMessaging\DeliveryStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\InboundMessage;

class WhatsAppNormalizer extends Normalizer
{
    public function normalize(array $entry): iterable
    {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            $channel = $value['metadata']['phone_number_id'] ?? null;
            if (! $channel) {
                continue;
            }
            foreach ($value['statuses'] ?? [] as $status) {
                if (isset($status['id']) && in_array($status['status'] ?? null, ['sent', 'delivered', 'read', 'failed'], true)) {
                    yield $channel => new DeliveryStatus($status['id'], $status['status'], $status['errors'][0] ?? null,
                        $this->timestamp($status['timestamp'] ?? null), $status['recipient_id'] ?? null);
                }
            }

            $contacts = $value['contacts'] ?? [];
            foreach (['messages', 'message_echoes'] as $key) {
                foreach ($value[$key] ?? [] as $message) {
                    $sender = $message['from'] ?? $message['from_parent_user_id'] ?? $message['from_user_id'] ?? null;
                    if ($sender === null || empty($message['type'])) {
                        continue;
                    }
                    $contact = collect($contacts)->first(fn ($c) => in_array($sender,
                        [$c['wa_id'] ?? null, $c['user_id'] ?? null, $c['parent_user_id'] ?? null], true));
                    if (! $contact && count($contacts) === 1 && count($value[$key]) === 1) {
                        $contact = $contacts[0];
                    }
                    yield $channel => $this->message($channel, $sender, $contact['profile']['name'] ?? null, $message, $key === 'message_echoes');
                }
            }
        }
    }

    private function message(string $channel, string $sender, ?string $name, array $message, bool $echo): InboundMessage
    {
        $type = $message['type'];
        $content = $message[$type] ?? [];
        $attachments = [];
        if (in_array($type, ['image', 'sticker', 'audio', 'video', 'document'], true)) {
            $attachments[] = [
                'type' => $type === 'document' ? 'file' : $type, 'url' => null,
                'mime' => $content['mime_type'] ?? null, 'id' => $content['id'] ?? null,
            ];
        }
        if ($type === 'location') {
            $attachments[] = ['type' => 'location', 'url' => $content['url'] ?? null, 'mime' => null, 'id' => $content['id'] ?? null];
        }
        if ($type === 'contacts') {
            foreach ($content as $card) {
                $attachments[] = ['type' => 'contact', 'url' => null, 'mime' => null, 'id' => null, 'contact' => $card];
            }
        }
        $text = match ($type) {
            'text' => $content['body'] ?? null,
            'button' => $content['text'] ?? null,
            'interactive' => $content['button_reply']['title'] ?? $content['list_reply']['title'] ?? $content['nfm_reply']['body'] ?? null,
            'reaction' => $content['emoji'] ?? null,
            'location' => $content['name'] ?? $content['address'] ?? null,
            'contacts' => $content[0]['name']['formatted_name'] ?? null,
            default => $content['caption'] ?? null,
        };

        return new InboundMessage(Platform::WhatsApp, $channel, $sender, $name, $message['id'] ?? null,
            $text, $attachments, $message['context']['id'] ?? $message['reaction']['message_id'] ?? null,
            null, $echo, $this->timestamp($message['timestamp'] ?? null), $message, $type, $content);
    }
}
