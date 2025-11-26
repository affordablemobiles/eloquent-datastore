<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueryBuilderTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testProjectionQueryReturnsOnlyProjectedAttributes(): void
    {
        // ARRANGE: Create a full user entity in the database.
        User::create([
            'name'  => 'Projected User',
            'email' => 'projected@example.com',
            'age'   => 30,
        ]);

        // ACTION: Filter on 'name', project 'name'.
        $user = User::query()
            ->select('age')
            ->first()
        ;

        // ASSERT:
        self::assertNotNull($user, 'The user model was not found.');

        // 1. Check that the projected attribute is present.
        self::assertSame(30, $user->age);

        // 2. Check that non-projected attributes are NULL.
        self::assertNull($user->name);
        self::assertNull(
            $user->email,
            "The 'email' attribute should be null as it was not projected."
        );
    }

    public function testKeysOnlyQueryReturnsModelsWithOnlyKeys(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $userKeysOnly = User::where('__key__', $user->id)->keysOnly()->first();

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
