<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Eloquent\Collection;
use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Order;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;

/**
 * Covers features from QueryController, DMLQueryController, and OrderJSONController.
 *
 * @internal
 *
 * @coversNothing
 */
final class AdvancedQueryTest extends TestCase
{
    protected array $kindsToClear = [
        Order::class,
        User::class,
    ];

    public function testKeysOnlyQueryReturnsModelsWithOnlyKeys(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $userKeysOnly = User::keysOnly()->find($user->id);

        // Model should exist
        self::assertNotNull($userKeysOnly);
        self::assertSame($user->id, $userKeysOnly->id);

        // But attributes should be null/empty
        self::assertNull($userKeysOnly->name);
        self::assertNull($userKeysOnly->email);
        self::assertEmpty($userKeysOnly->getAttributes());
    }

    public function testDistinctQueryPlucksUniqueValues(): void
    {
        User::create(['name' => 'John', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        User::create(['name' => 'John', 'email' => 'john2@example.com']);

        $distinctNames = User::distinct('name')->pluck('name');

        self::assertCount(2, $distinctNames);
        self::assertTrue($distinctNames->contains('John'));
        self::assertTrue($distinctNames->contains('Jane'));
    }

    public function testModelDestroyDeletesRecords(): void
    {
        $user1 = User::create(['name' => 'User 1']);
        $user2 = User::create(['name' => 'User 2']);

        self::assertSame(2, User::count());

        $deletedCount = User::destroy([$user1->id, $user2->id]);

        self::assertSame(2, $deletedCount);
        self::assertSame(0, User::count());
    }

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

        $users = User::where('name', 'like', 'Bulk User%')->get();
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

    public function testJsonCastingIsSavedAndRetrieved(): void
    {
        $details = [
            'test' => [
                'my_details' => ['first_name' => 'demo'],
            ],
        ];

        $order = Order::create(['details' => $details]);

        $found = Order::find($order->id);

        self::assertInstanceOf(\ArrayObject::class, $found->details);
        self::assertSame($details, (array) $found->details);
    }

    public function testJsonCastingCanBeModified(): void
    {
        $details = [
            'test' => [
                'my_details' => ['first_name' => 'demo'],
            ],
        ];

        $order = Order::create(['details' => $details]);

        // Modify the ArrayObject directly
        $order->details['test']['my_details']['first_name'] = 'stuart';
        $order->save();

        $found = Order::find($order->id);
        self::assertSame('stuart', $found->details['test']['my_details']['first_name']);
    }

    public function testExistsQueryReturnsCorrectly(): void
    {
        self::assertFalse(User::where('email', 'test@example.com')->exists());

        User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        self::assertTrue(User::where('email', 'test@example.com')->exists());
        self::assertFalse(User::where('email', 'other@example.com')->exists());
    }

    public function testLazyQueryIteratesCorrectly(): void
    {
        User::create(['name' => 'User 1']);
        User::create(['name' => 'User 2']);

        $results = User::orderBy('name')->lazy(1);
        $names   = [];
        foreach ($results as $user) {
            $names[] = $user->name;
        }

        self::assertCount(2, $names);
        self::assertSame('User 1', $names[0]);
    }

    public function testStatelessPaginationWithStartAndLastCursor(): void
    {
        // Create 4 users in a predictable order
        User::create(['name' => 'User 1']);
        User::create(['name' => 'User 2']);
        User::create(['name' => 'User 3']);
        User::create(['name' => 'User 4']);

        // --- Request 1: Get Page 1 ---
        $query1 = User::orderBy('name')->limit(2);
        $page1  = $query1->get();

        // Get the cursor to send to the "next page" request
        $cursor = $query1->lastCursor();

        self::assertCount(2, $page1);
        self::assertSame('User 1', $page1[0]->name);
        self::assertSame('User 2', $page1[1]->name);
        self::assertNotNull($cursor);
        self::assertIsString($cursor);

        // --- Request 2: Get Page 2 (Stateless) ---
        // Create a new query, simulating a new HTTP request
        $query2  = User::orderBy('name')->limit(2)->start($cursor);
        $page2   = $query2->get();
        $cursor2 = $query2->lastCursor();

        self::assertCount(2, $page2);
        self::assertSame('User 3', $page2[0]->name);
        self::assertSame('User 4', $page2[1]->name);
        self::assertNotNull($cursor2);

        // --- Request 3: Get Page 3 (Empty) ---
        $query3  = User::orderBy('name')->limit(2)->start($cursor2);
        $page3   = $query3->get();
        $cursor3 = $query3->lastCursor();

        self::assertCount(0, $page3);
        self::assertNull($cursor3); // No more results, cursor should be null
    }
}
