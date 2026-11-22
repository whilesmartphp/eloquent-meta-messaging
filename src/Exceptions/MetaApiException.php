<?php

namespace Whilesmart\MetaMessaging\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Whilesmart\MetaMessaging\Contracts\ResponseFormatter;

class MetaApiException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return app(ResponseFormatter::class)->failure('Meta could not complete this request (code '.$this->getCode().').', 502);
    }
}
