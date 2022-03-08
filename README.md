# Datastore Driver for Laravel

![Latest Stable Version](https://poser.pugx.org/a1comms/eloquent-datastore/v)
![License](https://poser.pugx.org/a1comms/eloquent-datastore/license)

A package for using Google Datastore as a database driver.

---
By using this package, you can use `query builder` and `eloquent` to access data from datastore.

## Installation

**This package requires Laravel 9.x & PHP 8.1 as a minimum.**

You can install the package via composer:

```bash
composer require a1comms/eloquent-datastore
```

If you are using Laravel Package Auto-Discovery, you don't need you to manually add the ServiceProvider.

#### Without auto-discovery:

If you don't use auto-discovery, add the below ServiceProvider to the `$providers` array in `config/app.php` file.

```php
A1comms\EloquentDatastore\DatastoreServiceProvider::class,
```

## Roadmap
- [ ] Read data using query builder.
- [x] Read data using eloquent model.
- [x] Insert data using eloquent model.
- [x] Update data using eloquent model.
- [ ] Delete data
- [x] Auto-generated primary key
- [ ] Cursor Paginate.
- [ ] Ancestor key relations.

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

You need to extend `A1comms\EloquentDatastore\Eloquent\Model` class instead of laravel's default eloquent model class.

**Example-**
```php
<?php

namespace App\Models;

use A1comms\EloquentDatastore\Eloquent\Model;

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
- [ ] connection
- [ ] table
- [ ] from
- [ ] select (for projection query)
- [ ] kind (same as table)
- [ ] where (Available:  = , > , < , >= , <= )
- [ ] limit
- [ ] take
- [ ] skip
- [ ] orderBy
- [ ] get
- [ ] simplePaginate
- [ ] paginate (works same as simplePaginate)
- [ ] first
- [ ] delete
- [ ] insert
- [ ] upsert
- [ ] find / lookup

## Contribution Guide

This driver is still not stable. You can contribute by reporting bugs, fixing bugs, reviewing pull requests and more ways.
Go to **issues** section, and you can start working on an issue immediately.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.