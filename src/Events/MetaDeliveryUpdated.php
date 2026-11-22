<?php

namespace Whilesmart\MetaMessaging\Events;

use Whilesmart\MetaMessaging\DeliveryStatus;
use Whilesmart\MetaMessaging\Models\MetaChannel;

final readonly class MetaDeliveryUpdated
{
    public function __construct(
        public MetaChannel $channel,
        public DeliveryStatus $status,
    ) {}
}
