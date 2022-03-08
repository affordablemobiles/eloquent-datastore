<?php

declare(strict_types=1);

namespace Appsero\LaravelDatastore;

use Illuminate\Support\ServiceProvider;

class DatastoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('db', function ($db): void {
            $db->extend('datastore', function ($config, $name) {
                $config['name'] = $name;

                return new DatastoreConnection($config);
            });
        });
    }
}
