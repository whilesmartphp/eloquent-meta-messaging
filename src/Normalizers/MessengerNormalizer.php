<?php

namespace Whilesmart\MetaMessaging\Normalizers;

use Whilesmart\MetaMessaging\Enums\Platform;

class MessengerNormalizer extends PageNormalizer
{
    protected function platform(): Platform
    {
        return Platform::Messenger;
    }
}
