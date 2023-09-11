#  Cerbos Laravel SDK

[![Latest Stable Version](http://poser.pugx.org/cerbos/cerbos-sdk-laravel/v)](https://packagist.org/packages/cerbos/cerbos-sdk-laravel)
[![Total Downloads](http://poser.pugx.org/cerbos/cerbos-sdk-laravel/downloads)](https://packagist.org/packages/cerbos/cerbos-sdk-laravel)
[![License](http://poser.pugx.org/cerbos/cerbos-sdk-laravel/license)](https://packagist.org/packages/cerbos/cerbos-sdk-laravel)

Cerbos Laravel SDK provides cerbos service provider and configuration for using Cerbos with laravel.

## Installation

You can install the SDK via [Composer](https://getcomposer.org/). Run the following command:
```bash
composer require cerbos/cerbos-sdk-laravel
```

The `CerbosServiceProvider` is auto-discovered and registered by default.

But, it is also possible to manually register the `CerbosServiceProvider` too by adding it to `config/app.php`.
```php
    'providers' => ServiceProvider::defaultProviders()->merge([
        // ...
        \Cerbos\Sdk\Laravel\Provider\CerbosServiceProvider::class,
    ])->toArray(),
```

Use the artisan `vendor` command which will create the `config/cerbos.php` for customizing the cerbos configuration.
```bash
php artisan vendor:publish
```