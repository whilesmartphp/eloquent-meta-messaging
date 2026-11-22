<?php

namespace Whilesmart\MetaMessaging\Http\Requests;

class FacebookConnectRequest extends ConnectChannelRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'redirect_uri' => ['required', 'url'],
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['required', 'string', 'distinct'],
            'platform' => ['required', 'in:messenger,instagram'],
        ];
    }
}
