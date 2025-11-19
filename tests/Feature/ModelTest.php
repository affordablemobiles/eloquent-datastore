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
final class ModelTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testModelDestroyDeletesRecords(): void
    {
        $user1 = User::create(['name' => 'User 1']);
        $user2 = User::create(['name' => 'User 2']);

        self::assertSame(2, User::count());

        $deletedCount = User::destroy([$user1->id, $user2->id]);

        self::assertSame(2, $deletedCount);
        self::assertSame(0, User::count());
    }
}
