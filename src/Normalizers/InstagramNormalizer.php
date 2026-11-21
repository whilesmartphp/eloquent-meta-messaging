<?php

namespace Whilesmart\MetaMessaging\Normalizers;

use Whilesmart\MetaMessaging\Enums\Platform;

class InstagramNormalizer extends PageNormalizer
{
    protected function platform(): Platform
    {
        return Platform::Instagram;
    }
}
