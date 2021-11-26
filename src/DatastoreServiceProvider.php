<?php

namespace Appsero\LaravelDatastore;

use Illuminate\Support\ServiceProvider;

class DatastoreServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('datastore', function ($config, $name) {
                $config['name'] = $name;

                return new DatastoreConnection($config);
            });
        });
    }
}
