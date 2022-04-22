<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BasketCached as Basket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BasketCachedController extends Controller
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
     * fetchMulti.
     *
     * Fetch the basket multiple times,
     *  to check subsequent requests come from the cache.
     */
    public function fetchMulti(): void
    {
        // Get the basket_id from the session.
        $id = session('basket_id', false);

        // Attempt to find the users basket.
        $basket = false;
        if ($id) {
            try {
                // Fetch the first basket from Datastore...
                $basket[] = Basket::findOrFail($id);
                // Fetch the same Basket again,
                //  and this time it should come from the cache...
                $basket[] = Basket::findOrFail($id);
                // Same again, from the cache...
                $basket[] = Basket::findOrFail($id);

                dd([
                    'basket'  => $basket,
                ]);
            } catch (ModelNotFoundException $ex) {
                dd('Empty Basket');
            }
        }

        dd('Empty Basket');
    }

    /**
     * fetchMultiAndUpdate.
     *
     * Fetch the basket multiple times,
     *  to check subsequent requests come from the cache.
     */
    public function fetchMultiAndUpdate(): void
    {
        // Get the basket_id from the session.
        $id = session('basket_id', false);

        // Attempt to find the users basket.
        $basket = false;
        if ($id) {
            try {
                // Fetch the first basket from Datastore...
                $basket[] = Basket::findOrFail($id);
                // Fetch the second basket from the cache...
                $basket[] = Basket::findOrFail($id);
                // Update this basket with a random value,
                $basket[1]->tariff_id = random_int(1, 5000);
                // then save it, both to Datastore & the cache.
                $basket[1]->save();
                // Fetch another basket, from the freshly saved cache...
                $basket[] = Basket::findOrFail($id);
                // Update this basket with a random value,
                $basket[1]->tariff_id = random_int(1, 5000);
                // then save it, both to Datastore & the cache.
                $basket[1]->save();
                // Fetch another basket, from the freshly saved cache...
                $basket[] = Basket::findOrFail($id);

                // $basket[1] and $basket[3] should be equal
                // This request should result in 3 calls to Datastore,
                // 1 x `lookup` call, 2 x `upsert` calls.
                dd([
                    'basket'  => $basket,
                ]);
            } catch (ModelNotFoundException $ex) {
                dd('Empty Basket');
            }
        }

        dd('Empty Basket');
    }

    /**
     * fetchFreshRefresh.
     *
     * Fetch the basket multiple times,
     *  then try a fresh/refresh to refresh the data
     *  from Datastore directly (flushing the cache for the single record)
     */
    public function fetchFreshRefresh(): void
    {
        // Get the basket_id from the session.
        $id = session('basket_id', false);

        // Attempt to find the users basket.
        $basket = false;
        if ($id) {
            try {
                // Fetch the first basket from Datastore...
                $basket['initial'] = Basket::findOrFail($id);
                // Fetch the second basket from the cache...
                $basket['refresh'] = Basket::findOrFail($id);

                // Now imagine you're making a Guzzle call here,
                //  that is modifying the state of your model externally.
                //      GuzzleHttp::post(...)

                // Fetch a fresh copy of the initial model...
                $basket['fresh'] = $basket['initial']->fresh();

                // Refresh this model in place...
                $basket['refresh']->refresh();

                // In the Trace data, you should see 3 x `lookup` calls.
                dd([
                    'basket'  => $basket,
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
