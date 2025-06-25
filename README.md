# Flyron

Flyron is a PHP package for `asynchronous programming` using `Fibers` and `process-based` concurrency, specially designed for Laravel applications.

## Install via composer

Run the following command to pull in the latest version:

```bash
composer require jobmetric/flyron
```

### Publish the config
Copy the `config` file from `vendor/jobmetric/flyron/config/config.php` to `config` folder of your Laravel application and rename it to `flyron.php`

Run the following command to publish the package config file:

```bash
php artisan vendor:publish --provider="JobMetric\Flyron\FlyronServiceProvider" --tag="flyron-config"
```

You should now have a `config/flyron.php` file that allows you to configure the basics of this package.

## Documentation

coming soon...
