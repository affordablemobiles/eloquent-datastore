<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\People;
use Illuminate\Support\Facades\DB;

class DMLQueryController extends Controller
{
    public function index(): void
    {
        dd([
            'dml' => true,
        ]);
    }

    public function insert()
    {
        // Insert a single entry using the query builder...
        DB::connection('datastore')
            ->table('laravel-query')
            ->insert([
                'first_name' => 'Samuel',
                'last_name'  => 'Melrose',
            ])
        ;

        // Insert multiple entries using the query builder...
        DB::connection('datastore')
            ->table('laravel-query')
            ->insert([
                [
                    'first_name' => 'Elisa',
                    'last_name'  => 'Melrose',
                ],
                [
                    'first_name' => 'Christopher',
                    'last_name'  => 'Melrose',
                ],
                [
                    'first_name' => 'Harper',
                    'last_name'  => 'Melrose',
                ],
            ])
        ;

        // Insert a new entry using the model
        //  and get the resulting key ID (insertGetId).
        // Start by creating & filling the object...
        $person             = new People();
        $person->first_name = 'Harold';
        $person->last_name  = 'Fisher';
        // Save it...
        $person->save();
        // You can now acces the auto-generated key ID here...
        $id = $person->id;

        return redirect()->route('query.index');
    }

    public function update()
    {
        // Fetch a single model object...
        $person = People::where('first_name', 'Samuel')->first();

        // Update a field...
        $person->first_name = 'Samantha';

        // Save it.
        $person->save();

        return redirect()->route('query.index');
    }

    public function updateMulti()
    {
        // Here we fetch a collection of model objects...
        $results = People::where('first_name', 'Samuel')->get();

        // Loop through them by reference...
        foreach ($results as &$item) {
            // Change the first name on every record...
            $item->first_name = 'Samantha';
        }

        // Then save the whole collection at once.
        $results->save();

        return redirect()->route('query.index');
    }

    public function deleteFirst()
    {
        // Fetch a single model instance...
        // Note: keysOnly() as we don't need the entire object
        //          and it makes this query a lot faster & free.
        $model = People::where('last_name', 'Melrose')
            ->keysOnly()->first()
        ;

        // And delete it...
        $model->delete();

        return redirect()->route('query.index');
    }

    public function delete()
    {
        // Fetch an entire collection of models...
        // Note: keysOnly() as we don't need the entire object
        //          and it makes this query a lot faster & free.
        $modelCollection = People::where('last_name', 'Melrose')
            ->keysOnly()->get()
        ;

        // And delete them all...
        $modelCollection->delete();

        return redirect()->route('query.index');
    }
}
