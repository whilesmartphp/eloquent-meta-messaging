<?php

namespace Whilesmart\MetaMessaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Whilesmart\MetaMessaging\Exceptions\MetaApiException;

class GraphClient
{
    public function request(string $method, string $path, ?string $token = null, array $parameters = [], bool $instagram = false): array
    {
        $base = config($instagram ? 'meta-messaging.instagram_base_url' : 'meta-messaging.graph_base_url');
        $prefix = $path === 'refresh_access_token' ? '' : '/'.config('meta-messaging.graph_version');
        $request = Http::baseUrl(rtrim($base, '/').$prefix)->acceptJson()->withoutRedirecting();
        if ($token !== null) {
            $request->withToken($token);
        }

        try {
            $response = $request->send($method, $path, $parameters ? [
                $method === 'GET' ? 'query' : 'json' => $parameters,
            ] : []);
        } catch (ConnectionException) {
            throw new MetaApiException('Could not reach Meta.');
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || isset($data['error'])) {
            $error = $data['error'] ?? [];
            throw new MetaApiException(
                is_string($error) ? $error : ($error['message'] ?? 'Invalid Graph response.'),
                (int) ($error['code'] ?? 0),
            );
        }

        return $data;
    }

    public function records(string $path, string $token): iterable
    {
        $query = [];
        $seen = [];
        do {
            $response = $this->request('GET', $path, $token, $query);
            yield from $response['data'] ?? throw new MetaApiException('Missing Graph collection.');
            $after = empty($response['paging']['next']) ? null : ($response['paging']['cursors']['after'] ?? null);
            if ($after !== null && isset($seen[$after])) {
                throw new MetaApiException('Repeated Graph pagination cursor.');
            }
            $seen[$after] = true;
            $query = ['after' => $after];
        } while ($after !== null);
    }

    public function subscription(string $id, string $token, ?array $fields, bool $instagram = false): void
    {
        $result = $this->request($fields === null ? 'DELETE' : 'POST', $id.'/subscribed_apps', $token,
            $fields === null ? [] : ['subscribed_fields' => $fields], $instagram);
        if (($result['success'] ?? false) !== true) {
            throw new MetaApiException('Meta did not confirm the subscription change.');
        }
    }
}
