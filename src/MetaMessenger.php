<?php

namespace Whilesmart\MetaMessaging;

use Carbon\CarbonInterface;
use InvalidArgumentException;
use Whilesmart\MetaMessaging\Enums\ChannelStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;
use Whilesmart\MetaMessaging\Exceptions\OutsideReplyWindow;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class MetaMessenger
{
    public function __construct(private GraphClient $graph, private InstagramTokenRefresh $tokens) {}

    public function send(MetaChannel $channel, string $recipientId, OutboundMessage $message, ?CarbonInterface $lastInboundAt = null): string
    {
        if ($channel->trashed() || $channel->status !== ChannelStatus::Connected) {
            throw new MetaApiException('Channel is disconnected.');
        }
        $whatsapp = $channel->platform === Platform::WhatsApp;
        $type = $this->messagingType($channel->platform, $message, $lastInboundAt);
        $direct = $channel->platform === Platform::Instagram && $channel->business_account_id === null;
        $path = $whatsapp || $direct ? $channel->external_id.'/messages' : 'me/messages';
        $body = $whatsapp ? $this->whatsapp($recipientId, $message) : $this->page($recipientId, $message, $type);
        $result = $this->graph->request('POST', $path, $this->tokens->token($channel), $body, $direct);
        $id = $whatsapp ? ($result['messages'][0]['id'] ?? null) : ($result['message_id'] ?? null);

        return $id ?? throw new MetaApiException('Meta returned no message ID.');
    }

    private function messagingType(Platform $platform, OutboundMessage $message, ?CarbonInterface $inbound): string
    {
        if ($message->type === 'template') {
            if ($platform !== Platform::WhatsApp) {
                throw new InvalidArgumentException('Templates require WhatsApp.');
            }

            return 'template';
        }
        $now = now()->toImmutable();
        if ($inbound && $inbound <= $now && $inbound >= $now->subDay()) {
            return 'RESPONSE';
        }
        if ($platform !== Platform::WhatsApp && config('meta-messaging.human_agent_tag')
            && $inbound && $inbound < $now && $inbound >= $now->subDays(7)) {
            return 'MESSAGE_TAG';
        }

        throw new OutsideReplyWindow('The recipient is outside the reply window.');
    }

    private function whatsapp(string $recipient, OutboundMessage $message): array
    {
        $address = preg_match('/^[A-Z]{2}\.(?:ENT\.)?[A-Za-z0-9]{1,128}$/D', $recipient)
            ? ['recipient_type' => 'individual', 'recipient' => $recipient] : ['to' => $recipient];
        $type = $message->type === 'file' ? 'document' : $message->type;
        $body = ['messaging_product' => 'whatsapp', 'type' => $type] + $address;
        if ($message->template !== null) {
            return $body + ['recipient_type' => 'individual', 'template' => $message->template];
        }
        $body['context'] = $message->replyTo === null ? null : ['message_id' => $message->replyTo];
        if ($type === 'text') {
            return $body + ['text' => ['body' => $message->text]];
        }
        $media = ['link' => $message->url];
        if ($type !== 'audio') {
            $media['caption'] = $message->text;
        }
        if ($type === 'document') {
            $media['filename'] = rawurldecode(basename(parse_url($message->url, PHP_URL_PATH) ?? 'file'));
        }

        return $body + [$type => $media];
    }

    private function page(string $recipient, OutboundMessage $message, string $type): array
    {
        $content = $message->type === 'text' ? ['text' => $message->text] : [
            'attachment' => ['type' => $message->type, 'payload' => ['url' => $message->url]],
        ];
        $body = ['recipient' => ['id' => $recipient], 'message' => $content, 'messaging_type' => $type];
        if ($message->replyTo !== null) {
            $body['reply_to'] = ['mid' => $message->replyTo];
        }
        if ($type === 'MESSAGE_TAG') {
            $body['tag'] = 'HUMAN_AGENT';
        }

        return $body;
    }
}
