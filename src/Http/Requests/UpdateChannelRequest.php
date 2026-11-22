<?php

namespace Whilesmart\MetaMessaging\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Whilesmart\OwnerAccess\Concerns\AuthorizesOwnerRequest;

class UpdateChannelRequest extends FormRequest
{
    use AuthorizesOwnerRequest;

    public function authorize(): bool
    {
        return $this->authorizeOwnerOfBoundModel('meta_channel');
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
