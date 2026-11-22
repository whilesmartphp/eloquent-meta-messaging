<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Whilesmart\MetaMessaging\Events\MetaDeliveryUpdated;
use Whilesmart\MetaMessaging\Events\MetaMessageReceived;
use Whilesmart\MetaMessaging\Jobs\ProcessWebhook;
use Whilesmart\MetaMessaging\Models\MetaChannel;

class WebhookTest extends TestCase
{
    public function test_verification_and_signature_checks(): void
    {
        $this->get('/meta/webhook?hub.mode=subscribe&hub.verify_token=ig-token&hub.challenge=123456')
            ->assertOk()->assertContent('123456');
        $this->get('/meta/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=123456')->assertForbidden();
        Queue::fake();
        $this->postFixture('instagram_text', 'sha256=invalid-signature')->assertUnauthorized();
        Queue::assertNothingPushed();
        config(['meta-messaging.queue_name' => 'incoming']);
        $this->postFixture('instagram_text')->assertOk();
        Queue::assertPushedOn('incoming', ProcessWebhook::class);
    }

    public function test_unknown_channel_is_ignored(): void
    {
        Event::fake([MetaMessageReceived::class]);
        $this->postFixture('whatsapp_text')->assertOk();
        Event::assertNotDispatched(MetaMessageReceived::class);
    }

    public static function messages(): array
    {
        return [
            ['whatsapp_text', 'test_phone_number_id', 'phone and bsuid', 'text'],
            ['whatsapp_attachment', 'test_phone_number_id', 'Check out my product!', 'image'],
            ['whatsapp_contacts', 'test_phone_number_id', 'Apple Inc.', 'contacts'],
            ['whatsapp_location', 'test_phone_number_id', 'Bay Bridge', 'location'],
            ['whatsapp_reaction', 'test_phone_number_id', '👍', 'reaction'],
            ['whatsapp_echo', 'test_phone_number_id', 'echo reply', 'text'],
            ['instagram_text', 'chatwoot-app-user-id-1', 'This is the first message from the customer', 'text'],
            ['instagram_attachment', 'chatwoot-app-user-id-1', null, 'share'],
            ['instagram_story_reply', 'chatwoot-app-user-id-1', 'This is the story reply', 'text'],
            ['instagram_story_mention', 'chatwoot-app-user-id-1', null, 'story_mention'],
            ['instagram_echo', 'Sender-id-1', null, 'story_mention'],
            ['instagram_reply', 'chatwoot-app-user-id-1', 'This is message with replyto mid', 'text'],
            ['messenger_text', '117172741761305', 'facebook message', 'text'],
        ];
    }

    #[DataProvider('messages')]
    public function test_signed_messages_are_normalized_and_deduplicated(string $fixture, string $external, ?string $text, string $type): void
    {
        $channel = MetaChannel::factory()->create(['platform' => explode('_', $fixture)[0], 'external_id' => $external]);
        Event::fake([MetaMessageReceived::class]);
        $this->postFixture($fixture)->assertOk();
        $this->postFixture($fixture)->assertOk();
        Event::assertDispatchedTimes(MetaMessageReceived::class, 1);
        Event::assertDispatched(MetaMessageReceived::class, function ($event) use ($channel, $fixture, $text, $type) {
            $message = $event->message;
            $this->assertSame($text, $message->text);
            $this->assertSame($type, $message->type);
            $this->assertSame(str_ends_with($fixture, '_echo'), $message->isEcho);
            match ($fixture) {
                'whatsapp_contacts' => $this->assertCount(2, $message->attachments),
                'whatsapp_location' => $this->assertSame(37.7893768, $message->details['latitude']),
                'whatsapp_text' => $this->assertSame('Muhsin', $message->senderName),
                'whatsapp_attachment' => $this->assertSame('image/jpeg', $message->attachments[0]['mime']),
                'instagram_story_reply' => $this->assertSame('chatwoot-app-user-id-1', $message->story['reply']['id']),
                'instagram_story_mention', 'instagram_echo' => $this->assertSame('https://www.example.com/test.jpeg', $message->story['mention']['url']),
                'instagram_reply' => $this->assertSame('message-id-1', $message->replyTo),
                default => null,
            };

            return $channel->is($event->channel) && $channel->external_id === $message->channelExternalId;
        });
    }

    public static function receipts(): array
    {
        return [
            ['whatsapp_status', 'test_phone_number_id', 'wamid.test', 'delivered'],
            ['instagram_read', 'chatwoot-app-user-id-1', 'message-id-1', 'read'],
            ['messenger_read', '117172741761305', null, 'read'],
            ['messenger_delivery', '117172741761305', null, 'delivered'],
        ];
    }

    #[DataProvider('receipts')]
    public function test_delivery_events(string $fixture, string $external, ?string $id, string $status): void
    {
        $channel = MetaChannel::factory()->create(['platform' => explode('_', $fixture)[0], 'external_id' => $external]);
        Event::fake([MetaDeliveryUpdated::class]);
        $this->postFixture($fixture)->assertOk();
        Event::assertDispatched(MetaDeliveryUpdated::class, fn ($e) => $channel->is($e->channel)
            && $e->status->messageId === $id && $e->status->status === $status
            && (! str_starts_with($fixture, 'messenger') || $e->status->at->getTimestamp() === 1648581633));
    }

    private function postFixture(string $name, ?string $signature = null)
    {
        $body = file_get_contents(__DIR__.'/../Fixtures/'.$name.'.json');

        return $this->call('POST', '/meta/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, config('meta-messaging.app_secret')),
        ], $body);
    }
}
