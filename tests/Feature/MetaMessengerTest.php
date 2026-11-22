<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;
use Whilesmart\MetaMessaging\Exceptions\OutsideReplyWindow;
use Whilesmart\MetaMessaging\MetaMessenger;
use Whilesmart\MetaMessaging\Models\MetaChannel;
use Whilesmart\MetaMessaging\OutboundMessage;

class MetaMessengerTest extends TestCase
{
    public static function sends(): array
    {
        $recipient = ['recipient' => ['id' => '1234']];
        $reply = ['reply_to' => ['mid' => 'message-id-1']];
        $url = 'https://www.example.com/test.jpeg';
        $media = ['message' => ['attachment' => ['type' => 'image', 'payload' => ['url' => $url]]]];
        $wa = ['messaging_product' => 'whatsapp', 'to' => '1234'];

        return [
            ['messenger', OutboundMessage::text('Hello'), $recipient + ['message' => ['text' => 'Hello'], 'messaging_type' => 'RESPONSE']],
            ['instagram', OutboundMessage::media('image', $url), $recipient + $media + ['messaging_type' => 'RESPONSE']],
            ['messenger', OutboundMessage::media('image', $url, 'message-id-1'), $recipient + $media + $reply + ['messaging_type' => 'RESPONSE']],
            ['whatsapp', OutboundMessage::text('Hello', 'message-id-1'), $wa + [
                'context' => ['message_id' => 'message-id-1'], 'type' => 'text', 'text' => ['body' => 'Hello'],
            ]],
            ['whatsapp', OutboundMessage::media('image', $url, caption: 'Look'), $wa + [
                'context' => null, 'type' => 'image', 'image' => ['link' => $url, 'caption' => 'Look'],
            ]],
            ['whatsapp', OutboundMessage::template('hello_world', 'en_US'), $wa + [
                'recipient_type' => 'individual', 'type' => 'template',
                'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US', 'policy' => 'deterministic'], 'components' => []],
            ]],
        ];
    }

    #[DataProvider('sends')]
    public function test_graph_request(string $platform, OutboundMessage $message, array $expected): void
    {
        $channel = MetaChannel::factory()->create(['platform' => $platform, 'business_account_id' => '123']);
        $result = $this->fixture('graph')['sent_'.$platform];
        Http::fake(['*/messages' => Http::response($result)]);
        $id = app(MetaMessenger::class)->send($channel, '1234', $message, $message->type === 'template' ? null : now());
        $this->assertSame($result['messages'][0]['id'] ?? $result['message_id'], $id);
        Http::assertSent(function (Request $request) use ($expected, $channel, $platform) {
            $this->assertEquals($expected, $request->data());

            return $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer '.$channel->access_token)
                && $request->url() === 'https://graph.facebook.com/v22.0/'.($platform === 'whatsapp' ? $channel->external_id : 'me').'/messages';
        });
    }

    public static function windows(): array
    {
        return [
            ['instagram', 24, false, 'RESPONSE'], ['messenger', 24, true, 'RESPONSE'],
            ['instagram', 25, false, null], ['instagram', 25, true, 'MESSAGE_TAG'],
            ['messenger', 168, true, 'MESSAGE_TAG'], ['messenger', 169, true, null],
            ['whatsapp', 24, true, 'whatsapp'], ['whatsapp', 25, true, null], ['whatsapp', null, false, null],
        ];
    }

    #[DataProvider('windows')]
    public function test_reply_window(string $platform, ?int $hours, bool $enabled, ?string $expected): void
    {
        $this->freezeTime();
        config(['meta-messaging.human_agent_tag' => $enabled]);
        Http::fake(['*/messages' => Http::response($this->fixture('graph')['sent_'.$platform])]);
        $channel = MetaChannel::factory()->create(['platform' => $platform, 'business_account_id' => '123']);
        try {
            app(MetaMessenger::class)->send($channel, '1234', OutboundMessage::text('hello'), $hours === null ? null : now()->subHours($hours));
        } catch (OutsideReplyWindow) {
            $this->assertNull($expected);
            Http::assertNothingSent();

            return;
        }
        $this->assertNotNull($expected);
        Http::assertSent(fn (Request $r) => ($r['messaging_type'] ?? $r['messaging_product']) === $expected
            && ($r['tag'] ?? null) === ($expected === 'MESSAGE_TAG' ? 'HUMAN_AGENT' : null));
    }

    public function test_graph_error_preserves_the_code_and_message(): void
    {
        $channel = MetaChannel::factory()->create(['platform' => 'instagram', 'business_account_id' => '123']);
        Http::fake(['*/messages' => Http::response($this->fixture('graph')['error'], 400)]);
        $this->expectException(MetaApiException::class);
        $this->expectExceptionCode(100);
        $this->expectExceptionMessage('Invalid message format');
        app(MetaMessenger::class)->send($channel, '1234', OutboundMessage::text('hello'), now());
    }
}
