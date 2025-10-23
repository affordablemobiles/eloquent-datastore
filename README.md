# Laravel Eloquent for Google Datastore

![Latest Stable Version](https://poser.pugx.org/affordablemobiles/eloquent-datastore/v)
![License](https://poser.pugx.org/affordablemobiles/eloquent-datastore/license)

A package for using Google Datastore as a database driver.

---
By using this package, you can use `query builder` and `eloquent` to access data from datastore.

## Installation

**This package requires PHP 8.3 as a minimum.**

This branch targets Laravel 12.x. For compatibility with other Laravel versions, please see the appropriate branch.

You can install the package via composer:

```bash
composer require affordablemobiles/eloquent-datastore
```

If you are using Laravel Package Auto-Discovery, you don't need you to manually add the ServiceProvider.

#### Without auto-discovery:

If you don't use auto-discovery, add the below ServiceProvider to the `$providers` array in `config/app.php` file.

```php
AffordableMobiles\EloquentDatastore\DatastoreServiceProvider::class,
```

## Roadmap
- [x] Read data using query builder.
- [x] Read data using Eloquent model.
- [x] Insert data using Eloquent model.
- [x] Update data using Eloquent model.
- [x] Delete data.
- [x] Keys only queries.
- [x] Auto-generated primary key.
- [x] Read multiple pages of data with Datastore cursors.
- [x] Batch update from Eloquent collection.
- [ ] `cursorPaginate` (Datastore cursors are used for `chunk` and `lazy`, but full pagination is not yet implemented)
- [x] Ancestor key relations.
- [x] Datastore Namespaces (Multi-Tenancy).

## Usage

You need to add `datastore` connection in `config/database.php` file.

```php
'connections' => [
    ...
    'datastore' => [
        'driver' => 'datastore',
        'transport' => env('DATASTORE_TRANSPORT', 'grpc'),
    ],
    ...
],
```

### Access using Eloquent Model

You need to extend `AffordableMobiles\EloquentDatastore\Eloquent\Model` class instead of Laravel's default Eloquent model class.

**Example-**
```php
<?php

namespace App\Models;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;

class Project extends Model
{
    // Your works here
}

```

### Access using Query Builder

**Example-**
```php
DB::connection('datastore')
    ->table('projects')
    ->where('project_id', '>', 5)
    ->skip(3)
    ->take(5)
    ->get();
```
It will return a collection.

### Understanding Primary Keys: `__key__` vs. `$primaryKey` vs. `id`

To use this driver effectively, it's important to understand how it handles Datastore's keys.

**1. The "Ground Truth": Datastore's `__key__`**

In Google Datastore, the true primary key for every entity is the `__key__` property. This is a complex `Key` object that contains the Kind, the scalar identifier (a string name or numeric ID), and any ancestor information. All final database operations (lookups, saves, deletes) *must* use this `Key` object.

**2. The Eloquent Layer (Recommended)**

The Eloquent `Model` (e.g., `App\Models\User`) provides a high-level, model-aware abstraction.

* **The `$primaryKey` is an ALIAS:** The `$primaryKey` property on your model (which defaults to `'id'`) is just a convenient **alias** for the scalar identifier (the string/int) part of the `__key__`.
* **Customization:** You can change this alias. If you set `protected $primaryKey = 'uuid';` on your `User` model, the driver will automatically handle all the mapping.
* **Two-Way Mapping:**
    * **Query (Out):** `User::where('uuid', 'my-uuid-string')->first()` is automatically translated by the driver into a `...WHERE __key__ = Key('User', 'my-uuid-string')` query.
    * **Hydration (In):** When the driver fetches data, it automatically populates the `uuid` attribute on your model with the key's identifier.

```php
// With: protected $primaryKey = 'uuid';

// GOOD: This all works as you'd expect.
$user = User::where('uuid', 'my-uuid-string')->first();
$user = User::find('my-uuid-string');
$user = User::firstOrCreate(['uuid' => 'my-uuid-string']);
$uuid = $user->uuid;
```

**3. The Base Query Builder (The "Escape Hatch")**

If you bypass Eloquent and use the base Query Builder (e.g., `DB::table('users')` or `User::query()->toBase()`), you are in a *model-agnostic* layer. This layer does **not** know about your model's `$primaryKey = 'uuid'` alias.

* **Fixed Convention**: To provide a consistent way to query keys at this level, the driver's query processor synthesizes the key's scalar identifier into a single, hardcoded property named `id`.
* **You MUST use `id`**: When using the base Query Builder, you **must** use `'id'` to query the key's identifier, regardless of what your Eloquent model's `$primaryKey` is set to.

```php
// GOOD: This works, even if the model's $primaryKey is 'uuid'.
$user = DB::table('users')->where('id', 'my-uuid-string')->first();

// BAD: This will NOT work.
// The query builder will look for a *data property* named 'uuid',
// not the entity's key, because it is model-agnostic.
$user = DB::table('users')->where('uuid', 'my-uuid-string')->first();
```

## Tested Builder Functions
- [x] `connection`
- [x] `table`
- [x] `from`
- [x] `namespace` (Datastore Namespace: Multi Tenancy)
- [x] `select` (for projection query)
- [x] `kind` (same as table)
- [x] `where` (Available:  = , > , < , >= , <= )
- [x] `limit` / `take`
- [x] `skip`
- [x] `orderBy`
- [x] `distinct`
- [x] `get`
- [x] `pluck`
- [x] `exists`
- [x] `count` (**Note:** This performs a keys-only query and counts the results; it is not a high-performance aggregation. Avoid using `count()` without `where` clauses on very large kinds, as it may be slow and costly.)
- [ ] `simplePaginate`
- [ ] `paginate` (works same as simplePaginate)
- [x] `first`
- [x] `delete`
- [x] `insert`
- [x] `_upsert` (different and incompatible with default `upsert`)
- [x] `find` / `lookup`
- [x] `chunk` / `chunkMap` / `each`
- [x] `lazy` / `cursor`

## Contribution Guide

You can contribute by reporting bugs, fixing bugs, submitting and reviewing pull requests.

Go to **issues** section, and you can start working on an issue immediately.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.