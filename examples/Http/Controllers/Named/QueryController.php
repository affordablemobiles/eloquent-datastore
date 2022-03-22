<?php

declare(strict_types=1);

namespace App\Http\Controllers\Named;

use App\Http\Controllers\Controller;
use App\Models\Named\People;
use Illuminate\Support\Facades\DB;

class QueryController extends Controller
{
    public const CHUNK_SIZE = 2;

    public function index(): void
    {
        // Raw query using the Query Builder.
        // Returns a collection of arrays of entity data.
        $rawCollection = DB::connection('datastore')
            ->table('laravel-query-named')
            ->where('last_name', 'Melrose')
            // ->orderBy('first_name') // disabled due to secondary index requirement.
            ->get()
        ;

        // Query the same data using an Eloquent model
        // Returns a collection of model objects...
        $modelCollection = People::where('last_name', 'Melrose')
            ->get()
        ;

        // Query for a single (the first) Eloquent model object
        $modelObject = People::where('last_name', 'Melrose')
            ->first()
        ;

        dd([
            'raw'       => $rawCollection,
            'models'    => $modelCollection,
            'first'     => $modelObject,
        ]);
    }

    public function limit(): void
    {
        // Get a collection of model objects, limited to max of 2.
        $modelCollection = People::where('last_name', 'Melrose')
            ->limit(2)
            ->get()
        ;

        dd([
            'models' => $modelCollection,
        ]);
    }

    public function skip(): void
    {
        // Get a collection of model objects, limited to max of 2,
        //  skipping the first 2 results.
        //  Equiv of `LIMIT 2, 2` in MySQL.
        $modelCollection = People::where('last_name', 'Melrose')
            ->limit(2)
            ->skip(2)
            ->get()
        ;

        dd([
            'models' => $modelCollection,
        ]);
    }

    public function distinct(): void
    {
        // Query for distinct first names
        //  see https://cloud.google.com/datastore/docs/concepts/queries#grouping
        $distinctCollection = People::distinct('first_name')
            ->pluck('first_name')
        ;

        dd([
            'models' => $distinctCollection,
        ]);
    }

    public function keysOnly(): void
    {
        // Get a collection of model objects,
        //  fetching only the key.
        // These queries are fast & free,
        //  mainly useful for DML operations.
        $keysOnly = People::where('last_name', 'Melrose')
            ->keysOnly()
            ->get()
        ;

        dd([
            'models' => $keysOnly,
        ]);
    }

    public function count(): void
    {
        // Get a count of objects that would
        //  be returned by the query.
        // This uses keysOnly() underneath so it's also free,
        //  but we do have to pull back all the objects to count them,
        //  so not quite as fast as MySQL, etc.
        $count = People::where('last_name', 'Melrose')
            ->count()
        ;

        dd([
            'count' => $count,
        ]);
    }

    public function exists(): void
    {
        // Check if any records exist for your query...
        // Equiv of $query->count() > 0
        $exists = People::where('last_name', 'Mel4rose')
            ->exists()
        ;

        dd([
            'exists' => $exists,
        ]);
    }

    public function chunk(): void
    {
        $arr = [];

        // Process all records returned by a query using a closure,
        //  in pages / chunks, specifying a chunk size of 2 entities at a time.
        People::where('last_name', 'Melrose')->chunk(self::CHUNK_SIZE, function ($results, $page) use (&$arr): void {
            foreach ($results as $result) {
                $arr[] = [
                    'first_name' => $result->first_name,
                    'last_name'  => $result->last_name,
                    'page'       => $page,
                ];
            }
        });

        dd(['final' => $arr]);
    }

    public function each(): void
    {
        $arr = [];

        // Process all records returned by a query using a closure,
        //  fetched from the DB as chunks of 2 entities at a time,
        //  handled by our closure one record at a time.
        People::where('last_name', 'Melrose')->each(function ($result) use (&$arr): void {
            $arr[] = [
                'first_name' => $result->first_name,
                'last_name'  => $result->last_name,
            ];
        }, self::CHUNK_SIZE);

        dd(['final' => $arr]);
    }

    public function lazy(): void
    {
        $arr = [];

        // Create a LazyCollection to read from the DB in chunks
        //  and we are specifying a chunk size of 2 entities at a time.
        $results = People::where('last_name', 'Melrose')->lazy(self::CHUNK_SIZE);

        // Loop through the results like you would for a normal collection
        //  and the results will be pulled in the background as required.
        foreach ($results as $result) {
            $arr[] = [
                'first_name' => $result->first_name,
                'last_name'  => $result->last_name,
            ];
        }

        dd(['final' => $arr]);
    }

    public function cursor(): void
    {
        $arr = [];

        // Create a LazyCollection to read from the DB in chunks
        //  with a fixed chunk size of 150 entities at a time.
        $results = People::where('last_name', 'Melrose')->cursor();

        // Loop through the results like you would for a normal collection
        //  and the results will be pulled in the background as required.
        foreach ($results as $result) {
            $arr[] = [
                'first_name' => $result->first_name,
                'last_name'  => $result->last_name,
            ];
        }

        dd(['final' => $arr]);
    }

    public function fresh(): void
    {
        $arr = [];

        // Fetch a collection of model objects...
        $results = People::where('last_name', 'Melrose')->get();

        // Return a collection with the same entities (by ID/key)
        //  but with fresh data from the DB.
        $newresults = $results->fresh();

        dd([
            'initial' => $results,
            'fresh'   => $newresults,
        ]);
    }

    public function freshFirst(): void
    {
        $arr = [];

        // Fetch a collection of model objects...
        $person = People::where('last_name', 'Melrose')->first();

        // Return a collection with the same entities (by ID/key)
        //  but with fresh data from the DB.
        $newperson = $person->fresh();

        dd([
            'initial' => $person,
            'fresh'   => $newperson,
        ]);
    }

    public function refreshFirst(): void
    {
        $arr = [];

        // Fetch a collection of model objects...
        $person = People::where('last_name', 'Melrose')->first();

        // Return a collection with the same entities (by ID/key)
        //  but with fresh data from the DB.
        $person->refresh();

        dd([
            'initial' => $person,
        ]);
    }
}
