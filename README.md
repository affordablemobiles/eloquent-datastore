# Datastore Driver for Laravel

![Latest Stable Version](https://poser.pugx.org/appsero/laravel-datastore/v)
![License](https://poser.pugx.org/appsero/laravel-datastore/license)

A package for using google datastore as a database driver.

---
By using this package, you can use `query builder` and `eloquent` to access data from datastore.

## Installation

You can install the package via composer:

```bash
composer require appsero/laravel-datastore
```

If you are using Laravel Package Auto-Discovery, you don't need you to manually add the ServiceProvider.

#### Without auto-discovery:

If you don't use auto-discovery, add the below ServiceProvider to the `$providers` array in `config/app.php` file.

```php
Appsero\LaravelDatastore\DatastoreServiceProvider::class,
```

## Roadmap
- Data read using query builder (available).
- Data read using eloquent model (available).
- Data insert (available).
- Data update (Using query builder, model coming soon).
- Data delete (available).
- Cursor Paginate (soon).
- Relations (soon).

## Usage

You need to add `datastore` connection in `config/database.php` file.

```php
'connections' => [
    ...
    'datastore' => [
        'driver' => 'datastore',
        'key_file_path' => env('GOOGLE_APPLICATION_CREDENTIALS', 'gcloud-credentials.json'),
        'prefix' => env('DATASTORE_PREFIX', null),
    ],
    ...
],
```

### Access using Eloquent Model

You need to extend `Appsero\LaravelDatastore\Eloquent\Model` class instead of laravel's default eloquent model class.

**Example-**
```php
<?php

namespace App\Models;

use Appsero\LaravelDatastore\Eloquent\Model;

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
- connection
- table
- from
- select (for projection query)
- kind (same as table)
- where (Available:  = , > , < , >= , <= )
- limit
- take
- skip
- orderBy
- get
- simplePaginate
- paginate (works same as simplePaginate)
- first
- delete
- insert
- upsert
- find / lookup

## Contribution Guide

This driver is still not stable. You can contribute by reporting bugs, fixing bugs, reviewing pull requests and more ways.
Go to **issues** section, and you can start working on a issue immediately.
If you want to add or fix something, open a pull request by following Laravel contribution guide.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.