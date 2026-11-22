<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

class MetaChannelAuthorizationTest extends TestCase
{
    public function test_an_inaccessible_owner_is_hidden_and_cannot_be_modified(): void
    {
        $this->fakeConnections();
        $id = $this->signup()->assertCreated()->json('data.id');
        $requests = Http::recorded()->count();
        $this->app->instance(OwnerAuthorizer::class, new class implements OwnerAuthorizer
        {
            public function authorize(?Authenticatable $user, string $ownerType, mixed $ownerId): bool
            {
                return false;
            }

            public function scope(Builder $query, ?Authenticatable $user, string $ownerTypeColumn = 'owner_type', string $ownerIdColumn = 'owner_id'): Builder
            {
                return $query->whereRaw('0 = 1');
            }
        });

        $path = '/api/meta-channels/'.$id;
        $this->getJson('/api/meta-channels')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->getJson($path)->assertForbidden();
        $this->patchJson($path, ['name' => 'Private'])->assertForbidden();
        $this->deleteJson($path)->assertForbidden();
        $this->signup()->assertForbidden();
        $this->facebook('instagram')->assertForbidden();
        Http::assertSentCount($requests);
    }
}
