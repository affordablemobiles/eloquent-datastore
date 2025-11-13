<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\Feature;

use AffordableMobiles\EloquentDatastore\Tests\TestCase;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\NamespacedOrder;
use AffordableMobiles\EloquentDatastore\Tests\TestModels\Order;
use Illuminate\Support\Facades\DB;

/**
 * Covers features from Models/Order.php (namespacing).
 *
 * @internal
 *
 * @coversNothing
 */
final class NamespaceTest extends TestCase
{
    protected array $kindsToClear = [
        Order::class,
    ];

    public function testModelIsSavedInCorrectNamespace(): void
    {
        $order   = NamespacedOrder::create(['name' => 'My Namespaced Order']);
        $orderId = $order->id;

        // 1. Assert it exists in its own model
        $found = NamespacedOrder::find($orderId);
        self::assertNotNull($found);
        self::assertSame('My Namespaced Order', $found->name);

        // 2. Assert it CANNOT be found by a model in the default namespace
        // (even though it's the same Kind 'namespaced_orders')
        $defaultNsFound = DB::table('namespaced_orders')->find($orderId);
        self::assertNull($defaultNsFound);

        // 3. Assert it CANNOT be found by a *different* model (e.g. Order)
        $wrongModelFound = Order::find($orderId);
        self::assertNull($wrongModelFound);

        // 4. Assert it can be found by a raw query specifying the namespace
        $rawFound = DB::connection('datastore')
            ->query()
            ->from('namespaced_orders')
            ->namespace('test-namespace')
            ->find($orderId)
        ;

        self::assertNotNull($rawFound);
        self::assertSame('My Namespaced Order', $rawFound['name']);
    }
}
