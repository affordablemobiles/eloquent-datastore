<?php

declare(strict_types=1);

namespace App\Http\Controllers\Named;

use App\Http\Controllers\Controller;
use App\Models\Named\Basket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BasketController extends Controller
{
    /**
     * index.
     *
     * View the basket.
     */
    public function index(): void
    {
        // Get the basket_id from the session.
        $id = session('basket_id', false);

        // Attempt to find the users basket.
        $basket = false;
        if ($id) {
            try {
                $basket = Basket::findOrFail($id);

                dd([
                    'basket' => $basket,
                ]);
            } catch (ModelNotFoundException $ex) {
                dd('Empty Basket');
            }
        }

        dd('Empty Basket');
    }

    /**
     * add.
     *
     * Add an item the basket.
     */
    public function add(Request $request)
    {
        // Has the user already got an item in the basket?
        // If not setup a new ID to use.
        $id = session('basket_id', uniqid('', true));

        // Find or create a basket with our ID...
        $basket = Basket::firstOrNew([
            'id' => $id,
        ]);

        // Prepare our input...
        $handset_id   = $request->input('handset_id', 0);
        $tariff_id    = $request->input('tariff_id', 0);
        $adnetwork    = $request->input('adnetwork', '');
        $campaign     = $request->input('campaign', '');

        // Set the data into the basket.
        $basket->handset_id   = (int) $handset_id;
        $basket->tariff_id    = (int) $tariff_id;
        $basket->adnetwork    = (string) $adnetwork;
        $basket->campaign     = (string) $campaign;

        // Save the data into the DB.
        $basket->save();

        // Save the (auto generated if new) basket id in the session.
        session(['basket_id' => $basket->id]);

        // Send the user to the basket index.
        return redirect()->route('basket.named.index');
    }
}
