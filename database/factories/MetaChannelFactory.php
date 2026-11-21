<?php

namespace Whilesmart\MetaMessaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Whilesmart\MetaMessaging\Enums\ChannelStatus;
use Whilesmart\MetaMessaging\Enums\Platform;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class MetaChannelFactory extends Factory
{
    public function modelName(): string
    {
        return config('meta-messaging.model', MetaChannel::class);
    }

    public function definition(): array
    {
        return [
            'owner_type' => 'workspace',
            'owner_id' => 1,
            'name' => $this->faker->company(),
            'platform' => Platform::Messenger,
            'external_id' => $this->faker->unique()->numerify('############'),
            'access_token' => 'test-page-token',
            'status' => ChannelStatus::Connected,
        ];
    }
}
