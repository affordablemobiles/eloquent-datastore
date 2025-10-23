# Laravel Eloquent for Google Datastore

![Latest Stable Version](https://poser.pugx.org/affordablemobiles/eloquent-datastore/v)
![License](https://poser.pugx.org/affordablemobiles/eloquent-datastore/license)

A package for using Google Datastore as a database driver.

---
By using this package, you can use `query builder` and `eloquent` to access data from datastore.

## Installation

**This package requires Laravel 9.x & PHP 8.1 as a minimum.**

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
- [x] `count` (**Note**: This performs a keys-only query and counts the results, it is not a high-performance aggregation. Avoid using count() without where clauses on very large kinds.)
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