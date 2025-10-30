<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;
use Illuminate\Support\Facades\DB;

/**
 * @internal
 *
 * @coversNothing
 */
final class BasicQueryTest extends TestCase
{
    protected array $kindsToClear = [
        User::class,
    ];

    public function testItCanCreateAnEntity(): void
    {
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        self::assertTrue($user->exists);
        self::assertNotNull($user->id);
        self::assertSame('Test User', $user->name);
    }

    public function testItCanFindAnEntityWithWhere(): void
    {
        $userId = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ])->id;

        $found = User::where('email', 'test@example.com')->first();

        self::assertNotNull($found);
        self::assertSame($userId, $found->id);
        self::assertSame('Test User', $found->name);
    }

    public function testItCanUpdateAnEntity(): void
    {
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->update(['name' => 'Updated Name']);

        $found = User::find($user->id);
        self::assertSame('Updated Name', $found->name);
    }

    public function testItCanDeleteAnEntityWithModel(): void
    {
        $user = User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);
        self::assertNotNull(User::find($user->id));

        $user->delete();

        self::assertNull(User::find($user->id));
    }

    public function testItCanDeleteAnEntityWithQuery(): void
    {
        User::create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::where('email', 'test@example.com')->delete();

        self::assertNull(User::where('email', 'test@example.com')->first());
    }

    public function testItCanCountEntities(): void
    {
        self::assertSame(0, User::count());

        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        self::assertSame(2, User::count());
        self::assertSame(1, User::where('email', 'user1@example.com')->count());
    }

    public function testItCanChunkResults(): void
    {
        User::create(['name' => 'User 1']);
        User::create(['name' => 'User 2']);
        User::create(['name' => 'User 3']);

        $names = [];
        User::chunk(2, static function ($users) use (&$names): void {
            foreach ($users as $user) {
                $names[] = $user->name;
            }
        });

        self::assertCount(3, $names);
        self::assertContains('User 1', $names);
        self::assertContains('User 2', $names);
        self::assertContains('User 3', $names);
    }

    public function testItCanUseTheBaseQueryBuilder(): void
    {
        DB::table('users')->insert([
            'name'  => 'Base Query User',
            'email' => 'base@example.com',
        ]);

        $user = DB::table('users')->where('email', 'base@example.com')->first();

        self::assertNotNull($user);
        self::assertSame('Base Query User', $user['name']);
    }
}
