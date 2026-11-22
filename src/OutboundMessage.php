<?php

namespace Whilesmart\MetaMessaging;

use InvalidArgumentException;

final readonly class OutboundMessage
{
    private function __construct(
        public string $type,
        public ?string $text = null,
        public ?string $url = null,
        public ?string $replyTo = null,
        public ?array $template = null,
    ) {}

    public static function text(string $text, ?string $replyTo = null): self
    {
        return new self('text', $text, replyTo: $replyTo);
    }

    public static function media(string $type, string $url, ?string $replyTo = null, ?string $caption = null): self
    {
        if (! in_array($type, ['image', 'audio', 'video', 'file'], true)) {
            throw new InvalidArgumentException('Unsupported media type.');
        }

        return new self($type, $caption, $url, $replyTo);
    }

    public static function template(string $name, string $language, array $components = []): self
    {
        return new self('template', template: [
            'name' => $name,
            'language' => ['policy' => 'deterministic', 'code' => $language],
            'components' => $components,
        ]);
    }
}
