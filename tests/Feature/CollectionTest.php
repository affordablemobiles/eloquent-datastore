<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Eloquent\Collection;
use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class CollectionTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testEloquentCollectionBulkInsertSave(): void
    {
        $collection = new Collection([
            User::make(['name' => 'Bulk User 1', 'email' => 'bulk1@example.com']),
            User::make(['name' => 'Bulk User 2', 'email' => 'bulk2@example.com']),
        ]);

        self::assertSame(0, User::count());

        $collection->save();

        self::assertSame(2, User::count());
        self::assertNotNull($collection[0]->id);
        self::assertNotNull($collection[1]->id);
        self::assertTrue($collection[0]->exists);
    }

    public function testEloquentCollectionBulkUpdateSave(): void
    {
        User::create(['name' => 'Bulk User 1', 'email' => 'bulk1@example.com']);
        User::create(['name' => 'Bulk User 2', 'email' => 'bulk2@example.com']);

        $users = User::get();
        self::assertCount(2, $users);

        $users[0]->name = 'Updated 1';
        $users[1]->name = 'Updated 2';

        $users->save();

        $found1 = User::find($users[0]->id);
        $found2 = User::find($users[1]->id);
        self::assertSame('Updated 1', $found1->name);
        self::assertSame('Updated 2', $found2->name);
    }

    public function testEloquentCollectionFresh(): void
    {
        $user = User::create(['name' => 'Test User']);

        $collection = User::where('id', $user->id)->get();
        $original   = $collection->first();
        self::assertSame('Test User', $original->name);

        // Simulate an external update
        $user->update(['name' => 'Updated Name']);

        // The original model in the collection is stale
        self::assertSame('Test User', $collection->first()->name);

        // Get a fresh collection
        $freshCollection = $collection->fresh();
        $fresh           = $freshCollection->first();

        self::assertNotSame($original, $fresh);
        self::assertSame('Updated Name', $fresh->name);
    }

    public function testEloquentCollectionDeleteDeletesAllModels(): void
    {
        // ARRANGE
        $user1 = User::create(['name' => 'User 1']);
        $user2 = User::create(['name' => 'User 2']);

        $users = User::findMany([$user1->id, $user2->id]);
        self::assertCount(2, $users);
        self::assertSame(2, User::count());

        // ACT
        $result = $users->delete();

        // ASSERT
        self::assertTrue($result);
        self::assertSame(0, User::count());
    }
}
