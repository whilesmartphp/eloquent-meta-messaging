<?php

namespace Whilesmart\MetaMessaging\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MetaChannelResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->only([
            'id', 'owner_type', 'owner_id', 'platform', 'external_id', 'business_account_id',
            'name', 'status', 'token_expires_at', 'metadata', 'created_at', 'updated_at',
        ]);
    }
}
