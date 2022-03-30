<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\People;

class AncestorQueryController extends Controller
{
    public function index(): void
    {
        $modelObject = People::where('first_name', 'Gary')
            ->firstOrFail()
        ;

        $data = [];

        foreach ($modelObject->children as $child) {
            $data[$modelObject->first_name][] = $child->first_name;
        }

        echo '<pre>'.json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function insert()
    {
        // Insert a new entry using the model
        //  and get the resulting key ID (insertGetId).
        // Start by creating & filling the object...
        $person             = new People();
        $person->first_name = 'Gary';
        $person->last_name  = 'Edwards';
        // Save it...
        $person->save();

        $person->children()->createMany([
            [
                'first_name' => 'Matthew',
                'last_name'  => 'Edwards',
            ],
            [
                'first_name' => 'Melissa',
                'last_name'  => 'Edwards',
            ],
        ]);

        return redirect()->route('query.ancestor.index');
    }

    public function updateChild()
    {
        $modelObject = People::where('first_name', 'Gary')
            ->firstOrFail()
        ;

        $child = $modelObject->children->firstOrFail();

        $child->first_name = 'Thomas';

        $child->save();

        return redirect()->route('query.ancestor.index');
    }

    public function parent(): void
    {
        $modelObject = People::where('first_name', 'Gary')
            ->firstOrFail()
        ;

        $child = $modelObject->children->firstOrFail();

        dd([
            'parent-parent' => $modelObject->parent,
            'child-parent'  => $child->parent,
        ]);
    }
}
