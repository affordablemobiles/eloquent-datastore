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
final class FindTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testFindReturnsFullModel(): void
    {
        // ARRANGE
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
            'age'   => 30,
        ]);
        self::assertNotNull($user->id);

        // ACT
        $foundUser = User::find($user->id);

        // ASSERT
        self::assertInstanceOf(User::class, $foundUser);
        self::assertSame($user->id, $foundUser->id);
        self::assertSame('Test User', $foundUser->name);
        self::assertSame('test@example.com', $foundUser->email);
        self::assertSame(30, $foundUser->age);
    }

    public function testFindWithProjectionReturnsPartialModel(): void
    {
        // ARRANGE
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
            'age'   => 30,
        ]);
        self::assertNotNull($user->id);

        // ACT: Select only name and age.
        $foundUser = User::find($user->id, ['id', 'name', 'age']);

        // ASSERT
        self::assertInstanceOf(User::class, $foundUser);
        self::assertSame($user->id, $foundUser->id);
        self::assertSame('Test User', $foundUser->name);
        self::assertSame(30, $foundUser->age);
        self::assertNull($foundUser->email, 'Email was not projected but was returned.');
    }

    public function testKeysOnlyFindReturnsOnlyKey(): void
    {
        // ARRANGE
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
            'age'   => 30,
        ]);
        self::assertNotNull($user->id);

        // ACT
        $foundUser = User::keysOnly()->find($user->id);

        // ASSERT
        self::assertInstanceOf(User::class, $foundUser);
        self::assertSame($user->id, $foundUser->id);
        self::assertNull($foundUser->name, 'Name was not projected but was returned.');
        self::assertNull($foundUser->email, 'Email was not projected but was returned.');
        self::assertNull($foundUser->age, 'Age was not projected but was returned.');
    }

    public function testKeysOnlyFindWithProjectionStillReturnsOnlyKey(): void
    {
        // ARRANGE
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
            'age'   => 30,
        ]);
        self::assertNotNull($user->id);

        // ACT: keysOnly should "win" over the projection.
        $foundUser = User::keysOnly()->find($user->id, ['id', 'name']);

        // ASSERT
        self::assertInstanceOf(User::class, $foundUser);
        self::assertSame($user->id, $foundUser->id);
        self::assertNull($foundUser->name, 'Name was not projected but was returned.');
        self::assertNull($foundUser->email, 'Email was not projected but was returned.');
    }

    // --- Batch Operations (findMany) ---

    public function testFindManyReturnsFullModels(): void
    {
        // ARRANGE
        $user1 = User::create(['name' => 'User 1', 'email' => '1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => '2@example.com']);

        // ACT
        $users = User::findMany([$user1->id, $user2->id]);

        // ASSERT
        self::assertCount(2, $users);

        // Use order-agnostic assertions on the returned collection
        $user1Found = $users->firstWhere('id', $user1->id);
        $user2Found = $users->firstWhere('id', $user2->id);

        self::assertNotNull($user1Found);
        self::assertSame('User 1', $user1Found->name);
        self::assertSame('1@example.com', $user1Found->email);

        self::assertNotNull($user2Found);
        self::assertSame('User 2', $user2Found->name);
        self::assertSame('2@example.com', $user2Found->email);
    }

    public function testFindManyWithProjectionReturnsPartialModels(): void
    {
        // ARRANGE
        $user1 = User::create(['name' => 'User 1', 'email' => '1@example.com', 'age' => 10]);
        $user2 = User::create(['name' => 'User 2', 'email' => '2@example.com', 'age' => 20]);

        // ACT: Select only name and age.
        $users = User::findMany([$user1->id, $user2->id], ['id', 'name', 'age']);

        // ASSERT
        self::assertCount(2, $users);

        // Use order-agnostic assertions
        $user1Found = $users->firstWhere('id', $user1->id);
        $user2Found = $users->firstWhere('id', $user2->id);

        self::assertNotNull($user1Found);
        self::assertSame('User 1', $user1Found->name);
        self::assertSame(10, $user1Found->age);
        self::assertNull($user1Found->email, 'Email was not projected but was returned.');

        self::assertNotNull($user2Found);
        self::assertSame('User 2', $user2Found->name);
        self::assertSame(20, $user2Found->age);
        self::assertNull($user2Found->email, 'Email was not projected but was returned.');
    }

    public function testKeysOnlyFindManyReturnsOnlyKeys(): void
    {
        // ARRANGE
        $user1 = User::create(['name' => 'User 1', 'email' => '1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => '2@example.com']);

        // ACT
        $users = User::keysOnly()->findMany([$user1->id, $user2->id]);

        // ASSERT
        self::assertCount(2, $users);

        // Use order-agnostic assertions
        $user1Found = $users->firstWhere('id', $user1->id);
        $user2Found = $users->firstWhere('id', $user2->id);

        self::assertNotNull($user1Found);
        self::assertSame($user1->id, $user1Found->id);
        self::assertNull($user1Found->name, 'Name was not projected but was returned.');
        self::assertNull($user1Found->email, 'Email was not projected but was returned.');

        self::assertNotNull($user2Found);
        self::assertSame($user2->id, $user2Found->id);
        self::assertNull($user2Found->name, 'Name was not projected but was returned.');
    }

    public function testKeysOnlyFindManyWithProjectionStillReturnsOnlyKeys(): void
    {
        // ARRANGE
        $user1 = User::create(['name' => 'User 1', 'email' => '1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => '2@example.com']);

        // ACT: keysOnly should "win" over the projection.
        $users = User::keysOnly()->findMany([$user1->id, $user2->id], ['id', 'name']);

        // ASSERT
        self::assertCount(2, $users);

        // Use order-agnostic assertions
        $user1Found = $users->firstWhere('id', $user1->id);
        $user2Found = $users->firstWhere('id', $user2->id);

        self::assertNotNull($user1Found);
        self::assertSame($user1->id, $user1Found->id);
        self::assertNull($user1Found->name, 'Name was not projected but was returned.');
        self::assertNull($user1Found->email, 'Email was not projected but was returned.');

        self::assertNotNull($user2Found);
        self::assertSame($user2->id, $user2Found->id);
        self::assertNull($user2Found->name, 'Name was not projected but was returned.');
    }
}
