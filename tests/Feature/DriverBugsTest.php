<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Pagination\Cursor;
use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use Google\Cloud\Datastore\Key;
use Illuminate\Support\Facades\DB;

/**
 * Tests for specific bugs we have fixed in the driver.
 *
 * @internal
 *
 * @coversNothing
 */
final class DriverBugsTest extends TestCase
{
    public function testDatastoreConnectionMagicCallReturnsValue(): void
    {
        // This tests the __call() method on DatastoreConnection.
        // It should proxy the call to the Google Client and return the value, not void.
        $key = DB::connection('datastore')->key('users', '123');

        self::assertInstanceOf(Key::class, $key);
    }

    public function testCursorFromEncodedHandlesInvalidString(): void
    {
        // This tests the safety check we added to Cursor::fromEncoded()

        // A malformed/invalid base64 string
        $invalidCursor = 'not-a-real-cursor-string';
        $result        = Cursor::fromEncoded($invalidCursor);
        self::assertNull($result);

        // A valid base64 string, but invalid JSON
        $invalidJsonCursor = base64_encode('{invalid-json:}');
        $result            = Cursor::fromEncoded($invalidJsonCursor);
        self::assertNull($result);

        // Valid JSON, but missing the required _pointsToNextItems key
        $missingKeyCursor = base64_encode(json_encode(['foo' => 'bar']));
        $result           = Cursor::fromEncoded($missingKeyCursor);
        self::assertNull($result);
    }
}
