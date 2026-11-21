<?php

namespace Whilesmart\MetaMessaging\Enums;

enum ChannelStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
