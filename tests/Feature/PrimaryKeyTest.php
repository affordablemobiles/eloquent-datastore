<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\UuidUser;
use Google\Cloud\Datastore\Key;
use Illuminate\Support\Facades\DB;

/**
 * @internal
 *
 * @coversNothing
 */
final class PrimaryKeyTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
        UuidUser::class,
    ];

    // Test the default 'id' primary key
    public function testItHandlesDefaultIdKey(): void
    {
        $user = User::create(['name' => 'Test User']);

        $found = User::find($user->id);

        self::assertSame($user->id, $found->id);
        self::assertSame('Test User', $found->name);
    }

    // Test our custom 'uuid' primary key
    public function testItCanCreateWithCustomUuidKey(): void
    {
        $user = UuidUser::create([
            'uuid' => 'my-custom-uuid-123',
            'name' => 'UUID User',
        ]);

        self::assertTrue($user->exists);
        self::assertSame('my-custom-uuid-123', $user->uuid);
    }

    public function testItCanFindWithCustomUuidKey(): void
    {
        UuidUser::create([
            'uuid' => 'my-uuid',
            'name' => 'UUID User',
        ]);

        $found = UuidUser::find('my-uuid');

        self::assertNotNull($found);
        self::assertSame('my-uuid', $found->uuid);
        self::assertSame('UUID User', $found->name);
    }

    public function testItCanWhereWithCustomUuidKey(): void
    {
        UuidUser::create([
            'uuid' => 'my-uuid-2',
            'name' => 'UUID User 2',
        ]);

        $found = UuidUser::where('uuid', 'my-uuid-2')->first();

        self::assertNotNull($found);
        self::assertSame('my-uuid-2', $found->uuid);
    }

    public function testFirstOrCreateWithCustomUuidKey(): void
    {
        // Test creating
        $user = UuidUser::firstOrCreate(
            ['uuid' => 'my-uuid-3'],
            ['name' => 'First Create']
        );
        self::assertSame('my-uuid-3', $user->uuid);
        self::assertSame('First Create', $user->name);

        // Test finding
        $user2 = UuidUser::firstOrCreate(
            ['uuid' => 'my-uuid-3'],
            ['name' => 'Should Not Create']
        );
        self::assertSame('my-uuid-3', $user2->uuid);
        self::assertSame('First Create', $user2->name);
    }

    // This tests the "escape hatch" documentation we wrote
    public function testBaseQueryBuilderUsesIdAliasForCustomKey(): void
    {
        $id = 'my-uuid-4';

        UuidUser::create([
            'uuid' => $id,
            'name' => 'Base Query UUID',
        ]);

        $key = (new UuidUser())->getKey($id);

        // This is the key insight: The base query builder MUST use 'id',
        // even though the model's primary key is 'uuid'.
        $found = DB::table('uuid_users')->where('id', $key)->first();

        self::assertNotNull($found);
        self::assertSame('Base Query UUID', $found['name']);
    }
}
