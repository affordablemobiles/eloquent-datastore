<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore;

use Illuminate\Support\ServiceProvider;

class DatastoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('db', static function ($db): void {
            $db->extend('datastore', static function ($config, $name) {
                $config['name'] = $name;

                return new DatastoreConnection($config);
            });
        });
    }
}
