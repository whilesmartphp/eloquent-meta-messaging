# whilesmart/eloquent-meta-messaging

Connect a workspace, organization, or other Eloquent owner to WhatsApp Cloud API, Instagram DMs, and Facebook Messenger. The package stores channel credentials and delivers messages to Laravel events. The host application owns conversation history.

## Install

```sh
composer require whilesmart/eloquent-meta-messaging
php artisan vendor:publish --tag=meta-messaging-config
php artisan migrate
```

Bind `Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer` to your application's tenant membership policy before enabling access. The default owner-access authorizer allows every owner. Add `Whilesmart\MetaMessaging\Traits\HasMetaChannels` to the owning model to query `$workspace->metaChannels()`. Configure an application encryption key and Sanctum authentication, or supply your own route middleware.

## Set up Meta

1. Create a Meta app for your business. Configure WhatsApp, Messenger, Instagram messaging with Facebook Login, and Facebook Login for Business for the products you use. Link each Instagram professional account to a Facebook Page.
2. Configure a valid OAuth redirect URI for Facebook Login. Create an Embedded Signup configuration for WhatsApp. Your frontend uses its configuration ID to start the flow. Validate OAuth state in the host application's callback before submitting the code.
3. Request the permissions for each integration. Page discovery uses `pages_show_list`. Page subscriptions use `pages_manage_metadata`. Request `pages_messaging` for Messenger, `instagram_basic` and `instagram_manage_messages` for Instagram, and `whatsapp_business_messaging` and `whatsapp_business_management` for WhatsApp. Complete Meta App Review for live use with customer accounts. [Page discovery](https://developers.facebook.com/docs/graph-api/reference/user/accounts/)
4. Set the callback URL for each webhook object to `https://your-app.example/meta/webhook` and enter the same verify token as your Laravel app. Select the WhatsApp Business Account message and coexistence echo fields. Select Page and Instagram message, echo, reaction and receipt fields offered for each product. Complete phone registration with Meta for numbers that still require provisioning. [Page webhook fields](https://developers.facebook.com/docs/graph-api/webhooks/reference/page/) and [Instagram webhook fields](https://developers.facebook.com/docs/graph-api/webhooks/reference/instagram)
5. Configure the host environment. Use an asynchronous queue connection in production and a cache shared by all workers that supports atomic locks.

```dotenv
META_APP_ID=your-app-id-here
META_APP_SECRET=your-app-secret-here
META_VERIFY_TOKEN=generate-a-secure-random-string-here
META_WHATSAPP_CONFIGURATION_ID=your-configuration-id-here
META_GRAPH_VERSION=v22.0
META_QUEUE_CONNECTION=redis
META_QUEUE_NAME=meta-messaging
META_HUMAN_AGENT_TAG=false
```

```sh
php artisan queue:work redis --queue=meta-messaging
```

The package uses the app's default queue connection and queue when the corresponding settings are empty. Meta calls use the configured Graph version. Instagram Login token refresh uses Meta's unversioned refresh endpoint.

## Connect and manage channels

The management routes default to `/api` and use `api` and `auth:sanctum`. Both connection endpoints require `owner_type` and `owner_id`. Use the same morph type your owner model returns from `getMorphClass()`. Send Meta IDs as strings.

| Method | Path | Input or result |
| --- | --- | --- |
| POST | `/api/meta-channels/whatsapp/embedded-signup` | Send the owner, authorization `code`, `business_account_id`, and `phone_number_id`. Returns one channel. |
| POST | `/api/meta-channels/facebook/connect` | Send the owner, `code`, `redirect_uri`, `page_ids` array, and `platform` of `messenger` or `instagram`. Returns the connected channels. |
| GET | `/api/meta-channels` | Lists accessible channels in `data.data` with pagination in `data.meta`. |
| GET | `/api/meta-channels/{id}` | Returns one accessible channel. |
| PATCH or PUT | `/api/meta-channels/{id}` | Updates the channel name. |
| DELETE | `/api/meta-channels/{id}` | Disconnects and soft deletes the channel. |

Each platform and external ID identifies one channel across all tenants. Reconnecting as the original owner restores a soft deleted row with the same ID. A different owner receives HTTP 409. Credentials are encrypted in the database and hidden from JSON. Channels on one WhatsApp business account or Facebook Page share a subscription. Removing the last channel unsubscribes the app. Each selected Page is connected individually. If Meta rejects a later Page, earlier successful connections remain available.

## Receive messages

Register listeners for `Whilesmart\MetaMessaging\Events\MetaMessageReceived` and `MetaDeliveryUpdated`. Both carry the channel, including its owner. The public webhook verifies Meta's challenge on GET and authenticates the original request bytes on POST. A valid POST queues parsing. Unknown and disconnected channels are ignored.

`$event->message` contains the platform, channel external ID, sender, optional sender name, message ID, text, attachments, reply target, story details, echo flag, timestamp, and original message data. Each attachment exposes `type`, `url`, `mime`, and `id` when delivered. WhatsApp media often supplies only an ID and MIME type. Contact cards, location fields and reactions are available in `details`. Use `isEcho` to distinguish messages sent by the business. Cache locks suppress duplicate inbound messages for 24 hours.

`$event->status` contains the message ID, status, error details, timestamp, and sender ID when supplied. Messenger can acknowledge a whole conversation through a watermark. In that case the message ID is null and the timestamp identifies the cutoff for that sender.

## Send messages

```php
use Whilesmart\MetaMessaging\MetaMessenger;
use Whilesmart\MetaMessaging\OutboundMessage;

$messenger = app(MetaMessenger::class);
$id = $messenger->send($channel, $recipientId, OutboundMessage::text('Your order is ready.', $replyToId), $lastInboundAt);

$photo = OutboundMessage::media('image', $publicImageUrl);
$messenger->send($channel, $recipientId, $photo, $lastInboundAt);

$template = OutboundMessage::template('order_ready', 'en_US', $components);
$messenger->send($whatsappChannel, $recipientId, $template);
```

Pass the time of the last incoming customer message for this recipient and channel. Text and media require a reply window. A missing timestamp closes that window. Instagram and Messenger send a standard response for the first 24 hours. With `human_agent_tag` enabled, human support replies can continue through day seven. Enable it only for eligible human replies. WhatsApp requires an approved template outside the first 24 hours. The sender returns Meta's message ID and throws `Exceptions\OutsideReplyWindow` for a closed window or `Exceptions\MetaApiException` for a Graph failure. Use `getCode()` and `getMessage()` for Meta's error. [Page Messages API](https://developers.facebook.com/docs/graph-api/reference/page/messages)

## Refresh credentials

Facebook Login creates Page credentials for both Messenger and linked Instagram channels. Reconnect these channels through Facebook Login when credentials become invalid. For a host provisioned Instagram Login channel, store the Instagram account as `external_id`, leave `business_account_id` null, and set `token_expires_at` with the long lived access token. Schedule the refresh command daily.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('meta-messaging:refresh-tokens')->daily();
```

Refresh applies to still valid Instagram Login tokens within ten days of expiry whose channel was last updated at least 24 hours ago. An expired token requires a new login.

## Customize

Set `channels_table` before migrating. Set `model`, `controller`, `requests`, `resource`, or `response_formatter` in the published configuration to use your own subclasses or formatter implementation. Routes support `register_routes`, `route_prefix`, `route_middleware`, `write_middleware`, `resource_actions`, and `connection_routes_enabled`. The webhook has independent `webhook_route_enabled`, `webhook_path`, and `webhook_middleware` settings.

The package is licensed under the MIT License. See NOTICE for the Chatwoot portions.
