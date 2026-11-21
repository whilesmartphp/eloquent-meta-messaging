<?php

namespace Whilesmart\MetaMessaging\ResponseFormatters;

use Illuminate\Http\JsonResponse;
use Whilesmart\MetaMessaging\Contracts\ResponseFormatter;

class DefaultResponseFormatter implements ResponseFormatter
{
    public function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $statusCode);
    }

    public function failure(string $message, int $statusCode): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $statusCode);
    }
}
