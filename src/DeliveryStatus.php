<?php

namespace Whilesmart\MetaMessaging;

use Carbon\CarbonImmutable;

final readonly class DeliveryStatus
{
    public function __construct(
        public ?string $messageId,
        public string $status,
        public ?array $error,
        public ?CarbonImmutable $at,
        public ?string $senderId = null,
    ) {}
}
