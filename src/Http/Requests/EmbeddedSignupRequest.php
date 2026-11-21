<?php

namespace Whilesmart\MetaMessaging\Http\Requests;

class EmbeddedSignupRequest extends ConnectChannelRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'business_account_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/D', 'max:255'],
            'phone_number_id' => ['required', 'string', 'max:255'],
        ];
    }
}
