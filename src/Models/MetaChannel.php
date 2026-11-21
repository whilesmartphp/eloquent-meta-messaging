<?php

namespace Whilesmart\MetaMessaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Whilesmart\MetaMessaging\Database\Factories\MetaChannelFactory;
use Whilesmart\MetaMessaging\Enums\ChannelStatus;
use Whilesmart\MetaMessaging\Enums\Platform;

class MetaChannel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['access_token'];

    protected $casts = [
        'platform' => Platform::class,
        'status' => ChannelStatus::class,
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('meta-messaging.channels_table', 'meta_channels');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): MetaChannelFactory
    {
        return MetaChannelFactory::new();
    }
}
