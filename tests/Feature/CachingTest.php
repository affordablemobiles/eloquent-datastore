<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\BasketCached;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Post;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;
use Google\Cloud\Datastore\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Covers features from BasketCachedController.
 *
 * @internal
 *
 * @coversNothing
 */
final class CachingTest extends TestCase
{
    protected array $kindsToClear = [
        BasketCached::class,
        Post::class,
        User::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Use the 'array' cache driver for these tests
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    public function testFindIsCachedOnSubsequentLookups(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket']);
        $basketId = $basket->id;

        // 1. Warm the cache (1 DB read)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit the cache (0 DB reads)
        $found2 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Hit the cache again (0 DB reads)
        $found3 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        self::assertSame($basketId, $found1->id);
        self::assertSame($basketId, $found2->id);
        self::assertSame($basketId, $found3->id);
    }

    public function testSaveInvalidatesModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Warm the cache (1 DB read)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertSame(100, $found1->tariff_id);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit the cache (0 DB reads)
        $found2 = BasketCached::findOrFail($basketId);
        self::assertSame(100, $found2->tariff_id);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Update the model (1 DB write)
        $found2->tariff_id = 200;
        $found2->save();
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 4. Cache is invalidated, so this is a new lookup (1 DB read)
        $found3 = BasketCached::findOrFail($basketId);
        self::assertSame(200, $found3->tariff_id);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 5. Cache is re-warmed, so this is cached (0 DB reads)
        $found4 = BasketCached::findOrFail($basketId);
        self::assertSame(200, $found4->tariff_id);
        self::assertCount(0, DB::getQueryLog());
    }

    public function testFreshBustsModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Warm the cache (1 DB read)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit the cache (0 DB reads)
        $found2 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Simulate external change
        DB::table('baskets')->where('id', $basketId)->update(['tariff_id' => 300]);

        // 4. Call fresh() - this must bust the cache (1 DB read)
        $fresh = $found2->fresh();
        self::assertSame(300, $fresh->tariff_id);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
    }

    public function testRefreshBustsModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Warm the cache (1 DB read)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Simulate external change
        DB::table('baskets')->where('id', $basketId)->update(['tariff_id' => 300]);

        // 3. Call refresh() - this must bust the cache (1 DB read)
        self::assertSame(100, $found1->tariff_id); // Is stale
        $found1->refresh();
        self::assertSame(300, $found1->tariff_id); // Is fresh
        self::assertCount(1, DB::getQueryLog());
    }

    /**
     * This test is refactored from 'testFindOnDescendantIsCached'
     * It now tests caching on a descendant by using a 'first' query,
     * as static 'find' cannot be used on descendants.
     */
    public function testFirstOnDescendantIsCached(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        // 1. Warm the cache (1 DB read)
        // We MUST query through the relation to get the ancestor context
        DB::enableQueryLog();
        $found1 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit the cache (0 DB reads)
        // We query through a fresh user instance to ensure it's stateless
        $freshUser = User::find($user->id);
        $found2    = $freshUser->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Verify it's the correct model (with ancestor key)
        self::assertSame($post->id, $found1->id);
        self::assertSame($post->id, $found2->id);
        self::assertTrue($found2->getKey()->ancestorKey() instanceof Key);
    }

    /**
     * This test is refactored from 'testUpdatingDescendantInvalidatesItsOwnCache'
     * It now uses a 'first' query, as static 'find' cannot be used on descendants.
     */
    public function testUpdatingDescendantInvalidatesItsOwnCache(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        // 1. Warm cache (1 DB read)
        DB::enableQueryLog();
        $found1 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertSame('Cache Post', $found1->title);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Update model (1 DB write)
        $found1->title = 'Updated Title';
        $found1->save();
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Cache is invalidated (1 DB read)
        $freshUser = User::find($user->id);
        $found2    = $freshUser->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertSame('Updated Title', $found2->title);
        self::assertCount(1, DB::getQueryLog());
    }

    public function testGetQueryOnDescendantIsCached(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $user->posts()->create(['title' => 'Cache Post']);

        // 1. Warm cache (1 DB read)
        DB::enableQueryLog();
        $posts1 = $user->posts()->get();
        self::assertCount(1, $posts1);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit cache (0 DB reads)
        $posts2 = $user->posts()->get();
        self::assertCount(1, $posts2);
        self::assertCount(0, DB::getQueryLog());
    }

    public function testUpdatingChildInvalidatesChildGetQueryCache(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        // 1. Warm cache (1 DB read)
        DB::enableQueryLog();
        $user->posts()->get();
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Update the child (1 DB write)
        $post->title = 'Updated Title';
        $post->save(); // This flushes tags for 'Post'
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Cache for $user->posts() should be flushed (1 DB read)
        $posts = $user->posts()->get();
        self::assertCount(1, DB::getQueryLog());
        self::assertSame('Updated Title', $posts->first()->title);
    }
}
