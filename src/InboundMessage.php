<?php

namespace Whilesmart\MetaMessaging;

use Carbon\CarbonImmutable;
use Whilesmart\MetaMessaging\Enums\Platform;

final readonly class InboundMessage
{
    public function __construct(
        public Platform $platform,
        public string $channelExternalId,
        public string $senderId,
        public ?string $senderName,
        public ?string $messageId,
        public ?string $text,
        public array $attachments,
        public ?string $replyTo,
        public ?array $story,
        public bool $isEcho,
        public ?CarbonImmutable $sentAt,
        public array $raw,
        public string $type,
        public array $details = [],
    ) {}
}
