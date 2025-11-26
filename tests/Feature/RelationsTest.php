<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Post;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\User;

/**
 * @internal
 *
 * @coversNothing
 */
final class RelationsTest extends TestCase
{
    protected array $kindsToClear = [
        Post::class,
        User::class,
    ];

    public function testItCanCreateDescendantEntities(): void
    {
        $user = User::create(['name' => 'User 1']);

        $post1 = $user->posts()->create(['title' => 'Post 1']);
        $post2 = $user->posts()->create(['title' => 'Post 2']);

        self::assertTrue($post1->exists);
        self::assertSame('Post 1', $post1->title);

        // Check the key path to verify it's a descendant
        $postKeyPath = $post1->getKey()->path();
        self::assertCount(2, $postKeyPath); // [User, Post]
        self::assertSame('users', $postKeyPath[0]['kind']);
        self::assertSame($user->id, $postKeyPath[0]['name'] ?? $postKeyPath[0]['id']);
        self::assertSame('posts', $postKeyPath[1]['kind']);
    }

    public function testItCanQueryDescendantEntities(): void
    {
        $user1 = User::create(['name' => 'User 1']);
        $user2 = User::create(['name' => 'User 2']);

        $user1->posts()->create(['title' => 'User 1 Post']);
        $user2->posts()->create(['title' => 'User 2 Post']);

        // Check User 1's posts
        $user1Posts = $user1->posts()->get();
        self::assertCount(1, $user1Posts);
        self::assertSame('User 1 Post', $user1Posts->first()->title);

        // Check User 2's posts
        $user2Posts = $user2->posts()->get();
        self::assertCount(1, $user2Posts);
        self::assertSame('User 2 Post', $user2Posts->first()->title);
    }

    public function testItCanQueryTheAncestor(): void
    {
        $user = User::create(['name' => 'User 1']);
        $post = $user->posts()->create(['title' => 'Post 1']);

        // Reload the post and test the relationship
        $foundPost = Post::find($post->getKey());
        $ancestor  = $foundPost->user()->first();

        self::assertNotNull($ancestor);
        self::assertInstanceOf(User::class, $ancestor);
        self::assertSame($user->id, $ancestor->id);
    }

    public function testFirstOrCreateOnDescendantRelation(): void
    {
        $user = User::create(['name' => 'User 1']);

        // Test creating
        $post1 = $user->posts()->firstOrCreate(
            ['title' => 'My First Post']
        );
        self::assertSame('My First Post', $post1->title);

        // Test finding
        $post2 = $user->posts()->firstOrCreate(
            ['title' => 'My First Post']
        );
        self::assertSame($post1->id, $post2->id);
        self::assertCount(1, $user->posts()->get());
    }

    public function testDeleteCollectionHandlesDescendants(): void
    {
        $user = User::create(['name' => 'User 1']);
        $user->posts()->create(['title' => 'Post 1']);
        $user->posts()->create(['title' => 'Post 2']);

        self::assertCount(2, $user->posts);

        // This was the bug we fixed in Eloquent\Collection::delete()
        $user->posts->delete();

        self::assertCount(0, $user->fresh()->posts);
    }
}
