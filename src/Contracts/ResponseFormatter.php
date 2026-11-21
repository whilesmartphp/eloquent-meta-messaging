<?php

namespace Whilesmart\MetaMessaging\Contracts;

use Illuminate\Http\JsonResponse;

interface ResponseFormatter
{
    public function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse;

    public function failure(string $message, int $statusCode): JsonResponse;
}
