<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Basket;
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
            $basket = Basket::find($id);

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
        $handset_id   = $request->input('handset_id', 0);
        $tariff_id    = $request->input('tariff_id', 0);
        $adnetwork    = $request->input('adnetwork', '');
        $campaign     = $request->input('campaign', '');

        // Create a new basket object to use as fallback
        //  if we don't find an existing one...
        $basket = new Basket();

        // Grab an existing basket ID from the session,
        //  if it exists...
        $id = session('basket_id', false);
        if ($id) {
            try {
                // Try to fetch the existing basket
                // from the DB to update it...
                $basket = Basket::findOrFail($id);
            } catch (ModelNotFoundException $ex) {
            }
        }

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
        return redirect()->route('basket.index');
    }
}
