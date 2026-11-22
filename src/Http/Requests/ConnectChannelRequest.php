<?php

namespace Whilesmart\MetaMessaging\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Whilesmart\OwnerAccess\Concerns\AuthorizesOwnerRequest;

abstract class ConnectChannelRequest extends FormRequest
{
    use AuthorizesOwnerRequest;

    public function authorize(): bool
    {
        return $this->authorizeOwnerInRequest();
    }

    public function rules(): array
    {
        return [
            'owner_type' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string'],
        ];
    }
}
