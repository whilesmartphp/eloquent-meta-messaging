<?php

namespace Whilesmart\MetaMessaging\Enums;

enum Platform: string
{
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Messenger = 'messenger';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
