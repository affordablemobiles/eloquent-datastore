<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\BasketCached;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Post;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;
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

        // 1. Verify creation already warmed the cache (0 DB reads)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Hit the cache again (0 DB reads)
        $found2 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 3. Manually clear cache to verify re-warming works (1 DB read)
        Cache::store('array')->flush();

        $found3 = BasketCached::findOrFail($basketId);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        self::assertSame($basketId, $found1->id);
        self::assertSame($basketId, $found2->id);
        self::assertSame($basketId, $found3->id);
    }

    public function testSaveInvalidatesModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Verify cache is warm from creation (0 DB reads)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertSame(100, $found1->tariff_id);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Update the model (1 DB write)
        $found1->tariff_id = 200;
        $found1->save();

        DB::flushQueryLog();

        // 3. Verify cache was updated (0 DB reads)
        // Since save() calls recacheFindQuery(), the new data should be in cache immediately.
        $found2 = BasketCached::findOrFail($basketId);
        self::assertSame(200, $found2->tariff_id);
        self::assertCount(0, DB::getQueryLog());

        // 4. Verify it is actually cached by clearing it and checking for a read
        Cache::store('array')->flush();
        $found3 = BasketCached::findOrFail($basketId);
        self::assertSame(200, $found3->tariff_id);
        self::assertCount(1, DB::getQueryLog());
    }

    public function testFreshBustsModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Warm the cache (0 DB reads because create warmed it)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Simulate external change using raw client
        $client              = DB::connection('datastore')->getClient();
        $key                 = $basket->getKey();
        $entity              = $client->lookup($key);
        $entity['tariff_id'] = 300;
        $client->upsert($entity);

        // 3. Call fresh() - this must bust the cache (1 DB read)
        $fresh = $found1->fresh();
        self::assertSame(300, $fresh->tariff_id);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
    }

    public function testRefreshBustsModelCache(): void
    {
        $basket   = BasketCached::create(['name' => 'Test Basket', 'tariff_id' => 100]);
        $basketId = $basket->id;

        // 1. Warm the cache (0 DB reads because create warmed it)
        DB::enableQueryLog();
        $found1 = BasketCached::findOrFail($basketId);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Simulate external change using raw client
        $client              = DB::connection('datastore')->getClient();
        $key                 = $basket->getKey();
        $entity              = $client->lookup($key);
        $entity['tariff_id'] = 300;
        $client->upsert($entity);

        // 3. Call refresh() - this must bust the cache (1 DB read)
        self::assertSame(100, $found1->tariff_id); // Is stale
        $found1->refresh();
        self::assertSame(300, $found1->tariff_id); // Is fresh
        self::assertCount(1, DB::getQueryLog());
    }

    public function testFirstOnDescendantIsCached(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        DB::enableQueryLog();

        // 1. Verify cache is warm from creation (0 DB reads)
        $found1 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();

        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Manually clear cache to verify we can look it up again
        Cache::store('array')->flush();

        // Use existing user instance to construct the relation query
        $found2 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();

        // Expect exactly 1 query for the Post lookup
        self::assertCount(1, DB::getQueryLog());

        // 3. Verify it's the correct model
        self::assertSame($post->id, $found1->id);
        self::assertSame($post->id, $found2->id);
    }

    public function testUpdatingDescendantInvalidatesItsOwnCache(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        // 1. Verify cache is warm (0 DB reads)
        DB::enableQueryLog();
        $found1 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertSame('Cache Post', $found1->title);
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Update model (1 DB write)
        $found1->title = 'Updated Title';
        $found1->save();

        DB::flushQueryLog();

        // 3. Verify cache is updated and hit (0 DB reads)
        $found2 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();

        self::assertSame('Updated Title', $found2->title);
        self::assertCount(0, DB::getQueryLog());

        // 4. Manually clear to prove it was cached (1 DB read)
        Cache::store('array')->flush();
        DB::flushQueryLog();

        $found3 = $user->posts()->where($post->getKeyName(), $post->id)->firstOrFail();
        self::assertCount(1, DB::getQueryLog());
    }

    /**
     * Tests that general queries (not by ID) are NOT cached.
     */
    public function testGetOnDescendantIsNotCached(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $user->posts()->create(['title' => 'Cache Post']);

        // 1. Run query (1 DB read)
        DB::enableQueryLog();
        $posts1 = $user->posts()->get();
        self::assertCount(1, $posts1);
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Run query again (1 DB read)
        // Since this is a general 'get()' without an ID filter,
        // it bypasses the lookup optimization and should NOT be cached.
        $posts2 = $user->posts()->get();
        self::assertCount(1, $posts2);
        self::assertCount(1, DB::getQueryLog());
    }

    /**
     * Tests that invalidating a child also invalidates cached lookups.
     */
    public function testUpdatingChildInvalidatesChildLookupCache(): void
    {
        $user = User::create(['name' => 'Cache User']);
        $post = $user->posts()->create(['title' => 'Cache Post']);

        // 1. Warm cache via lookup (0 reads, create() warmed it)
        DB::enableQueryLog();
        $user->posts()->where('id', $post->id)->first();
        self::assertCount(0, DB::getQueryLog());
        DB::flushQueryLog();

        // 2. Update the child (1 DB write)
        $post->title = 'Updated Title';
        $post->save(); // Recaches
        DB::flushQueryLog();

        // 3. Lookup should still be cached (0 reads) with new data
        $posts = $user->posts()->where('id', $post->id)->first();
        self::assertCount(0, DB::getQueryLog());
        self::assertSame('Updated Title', $posts->title);
    }
}
