<?php

namespace Whilesmart\MetaMessaging\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Whilesmart\MetaMessaging\ChannelConnections;
use Whilesmart\MetaMessaging\Contracts\ResponseFormatter;
use Whilesmart\MetaMessaging\Http\Requests\EmbeddedSignupRequest;
use Whilesmart\MetaMessaging\Http\Requests\FacebookConnectRequest;
use Whilesmart\MetaMessaging\Http\Requests\UpdateChannelRequest;
use Whilesmart\MetaMessaging\Models\MetaChannel;
use Whilesmart\OwnerAccess\Concerns\AuthorizesOwnerController;

class MetaChannelController extends Controller
{
    use AuthorizesOwnerController;

    public function __construct(private ResponseFormatter $responses) {}

    public function index(Request $request): JsonResponse
    {
        $channels = $this->scopeAccessibleOwners(config('meta-messaging.model')::query(), $request->user())
            ->orderByDesc('id')->paginate(max(1, min(100, $request->integer('per_page', 25))));
        $resource = config('meta-messaging.resource');

        return $this->responses->success($resource::collection($channels)->response()->getData(true));
    }

    public function show(Request $request, MetaChannel $meta_channel): JsonResponse
    {
        $this->authorizeAccessTo($meta_channel, $request->user());

        return $this->channelResponse($meta_channel);
    }

    public function update(UpdateChannelRequest $request, MetaChannel $meta_channel): JsonResponse
    {
        $this->authorizeAccessTo($meta_channel, $request->user());
        $meta_channel->update($request->safe()->only(['name']));

        return $this->channelResponse($meta_channel);
    }

    public function destroy(Request $request, MetaChannel $meta_channel, ChannelConnections $connections): JsonResponse
    {
        $this->authorizeAccessTo($meta_channel, $request->user());
        $connections->disconnect($meta_channel);

        return $this->responses->success(null, 'Channel disconnected.');
    }

    public function embeddedSignup(EmbeddedSignupRequest $request, ChannelConnections $connections): JsonResponse
    {
        return $this->channelResponse($connections->whatsapp($request->validated()), 201);
    }

    public function facebookConnect(FacebookConnectRequest $request, ChannelConnections $connections): JsonResponse
    {
        $resource = config('meta-messaging.resource');

        return $this->responses->success($resource::collection($connections->facebook($request->validated())), statusCode: 201);
    }

    private function channelResponse(MetaChannel $channel, int $status = 200): JsonResponse
    {
        $resource = config('meta-messaging.resource');

        return $this->responses->success(new $resource($channel), statusCode: $status);
    }
}
