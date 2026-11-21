<?php

namespace Whilesmart\MetaMessaging\Events;

use Whilesmart\MetaMessaging\InboundMessage;
use Whilesmart\MetaMessaging\Models\MetaChannel;

final readonly class MetaMessageReceived
{
    public function __construct(
        public MetaChannel $channel,
        public InboundMessage $message,
    ) {}
}
