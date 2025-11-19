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
final class ProtectionTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testItPreventsSavingPartialModelFromSelect(): void
    {
        $user = User::create(['name' => 'Full User', 'email' => 'test@example.com', 'age' => 25]);

        // Fetch partial model
        $partialUser = User::select(['name'])->find($user->id);

        self::assertTrue($partialUser->isPartial);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot save a partial model');

        // Attempting to save should throw
        $partialUser->save();
    }

    public function testItPreventsSavingPartialModelFromKeysOnly(): void
    {
        $user = User::create(['name' => 'Keys Only User']);

        // Fetch keys only
        $keysOnlyUser = User::query()->keys()->find($user->id);

        self::assertTrue($keysOnlyUser->isPartial);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot save a partial model');

        $keysOnlyUser->save();
    }

    public function testItAllowsDeletingPartialModel(): void
    {
        $user = User::create(['name' => 'To Delete']);

        $partialUser = User::select(['name'])->find($user->id);

        // Should NOT throw exception
        $partialUser->delete();

        self::assertNull(User::find($user->id));
    }
}
