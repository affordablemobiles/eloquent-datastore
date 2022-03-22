<?php

declare(strict_types=1);

namespace App\Http\Controllers\Named;

use App\Http\Controllers\Controller;
use App\Models\Named\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class OrderJSONController extends Controller
{
    /**
     * index.
     *
     * Dump the order details array,
     *  stored in the DB as a
     *  JSON encoded string.
     */
    public function index(): void
    {
        // Get the order_id from the session.
        $id = session('order_id', false);

        // Attempt to find relevant record from the DB...
        $order = false;
        if ($id) {
            try {
                $order = Order::findOrFail($id);

                // Now the details field should automatically
                //  be json_decode'd when we access it...
                dd([
                    // Cast to an array, as $order->details
                    //  here is actually an instance of
                    //  \Illuminate\Database\Eloquent\Casts\ArrayObject
                    //  as this allows updating how you'd expect,
                    //  see the `modify` method below, or the Laravel
                    //  docs for more information:
                    //  https://laravel.com/docs/9.x/eloquent-mutators#array-and-json-casting
                    'details' => (array) $order->details,
                ]);
            } catch (ModelNotFoundException $ex) {
                dd('Not Found');
            }
        }

        dd('Not Found');
    }

    /**
     * create.
     *
     * Create a new order object with a details
     *  array that is stored in the DB
     *  as a json_encode'd string.
     */
    public function create(Request $request)
    {
        // For testing (NOT PRODUCTION), we can grab
        //  multi-dementional array data from
        //  query string variables, e.g.
        //      /order/create?data[test][my_details][first_name]=demo
        //
        // $details = $request->input('data', []);

        // But in this instance,
        //  well just specify the array statically...
        $details = [
            'test' => [
                'my_details' => [
                    'first_name' => 'demo',
                ],
            ],
        ];

        // Create a new order object...
        $order = new Order();

        // Set a generated key/id...
        $order->id = uniqid('', true);

        // Set the data on the model attribute...
        $order->details = (array) $details;

        // Save the model back to the DB,
        //  at which point, the details array should be
        //  json_encode'd before writing.
        $order->save();

        // Save the order id in the session.
        session(['order_id' => $order->id]);

        // Send the user to the order index.
        return redirect()->route('order.named.index');
    }

    /**
     * modify.
     *
     * Example of modifying a single element of
     *  the order model's multi-dementional
     *  details array, that is stored in the DB
     *  as a json_encode'd string.
     */
    public function modify(Request $request)
    {
        // Create our variable so it has the correct scope.
        $order = null;

        // Grab the order ID from the session.
        $id = session('order_id', false);
        if ($id) {
            // Pull the model instance from the DB,
            //  at which point the details array is automatically
            //  json_decode'd before we start to read it, or on read(?).
            $order = Order::findOrFail($id);
        } else {
            throw new \LogicException('Order ID Not Found');
        }

        // Change a specific value in the multi-dementional array.
        $order->details['test']['my_details']['first_name'] = 'stuart';

        // Save back to the DB, at which point,
        //  the details array will be json_encode'd again before writing.
        $order->save();

        // Send the user to the order index.
        return redirect()->route('order.named.index');
    }
}
