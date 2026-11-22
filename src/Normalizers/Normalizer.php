<?php

namespace Whilesmart\MetaMessaging\Normalizers;

use Carbon\CarbonImmutable;

abstract class Normalizer
{
    abstract public function normalize(array $entry): iterable;

    protected function timestamp(mixed $value): ?CarbonImmutable
    {
        if (is_numeric($value)) {
            return $value >= 100000000000
                ? CarbonImmutable::createFromTimestampMs($value, 'UTC')
                : CarbonImmutable::createFromTimestamp($value, 'UTC');
        }

        return $value ? CarbonImmutable::parse($value, 'UTC') : null;
    }
}
