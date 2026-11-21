<?php

namespace Whilesmart\MetaMessaging\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMetaChannels
{
    public function metaChannels(): MorphMany
    {
        return $this->morphMany(config('meta-messaging.model'), 'owner');
    }
}
