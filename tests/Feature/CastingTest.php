<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Order;

/**
 * @internal
 *
 * @coversNothing
 */
final class CastingTest extends TestCase
{
    protected array $kindsToClear = [
        Order::class,
    ];

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
}
