## Running Tests

This directory contains the test suite for the Datastore Eloquent driver.

## 1. Requirements

* [PHPUnit](https://phpunit.de/) (installed via `composer install --dev`)
* [Orchestra Testbench](https://orchestraplatform.com/docs/testbench/) (installed via `composer install --dev`)
* A running Google Cloud Datastore Emulator.

## 2. Start the Emulator

Before running tests, you must start the local Datastore emulator.

1. Make sure you have the `gcloud` CLI installed.

2. Install the emulator component:

```
gcloud components install datastore-emulator
```

3. Start the emulator in a separate terminal:

```
gcloud emulators datastore start --project=test-project --host-port=localhost:8081
```

The `phpunit.xml` file is already configured to connect to the emulator at `localhost:8081` with the project ID `test-project`.

## 3. Run the Test Suite

With the emulator running, you can run the test suite from the root of the package:

```
composer test
```

Or, by calling PHPUnit directly:

```
./vendor/bin/phpunit
```
