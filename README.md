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

## Query plans

The Laravel SDK provides a `Cerbos` facade for building common authorization
requests from Laravel users and models.

```php
use Cerbos\Sdk\Laravel\Facades\Cerbos;

$expenses = Cerbos::plan(Expense::class)
    ->forUser(attributes: [
        'ipAddress' => request()->ip(),
    ])
    ->actions(['view'])
    ->applyTo(Expense::query())
    ->get();
```

For resource checks:

```php
$result = Cerbos::check($expense)
    ->forUser(attributes: [
        'mfa' => $request->user()->hasVerifiedMfa(),
    ])
    ->actions(['view', 'approve', 'delete', 'update'])
    ->send();

if ($result->isAllowed('approve')) {
    // ...
}
```

For single action checks:

```php
abort_if(Cerbos::notAllowed('delete', $expense), 403);

if (Cerbos::isAllowed('approve', $expense)) {
    // ...
}
```

Models can expose their Cerbos mapping with the `HasCerbosResource` trait:

```php
use Cerbos\Sdk\Laravel\Concerns\HasCerbosResource;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasCerbosResource;

    public function cerbosResourceKind(): string
    {
        return 'expense';
    }

    public function cerbosResourceAttributes(): array
    {
        return [
            'amount' => $this->amount,
            'region' => $this->region,
            'status' => $this->status,
            'ownerId' => $this->owner_id,
            'vendor' => $this->vendor,
        ];
    }

    public static function cerbosColumnMap(): array
    {
        return [
            'ownerId' => 'owner_id',
        ];
    }
}
```

User models can expose Cerbos principal data with `HasCerbosPrincipal`:

```php
use Cerbos\Sdk\Laravel\Concerns\HasCerbosPrincipal;

class User extends Authenticatable
{
    use HasCerbosPrincipal;

    public function cerbosPrincipalRoles(): array
    {
        return explode(',', $this->roles);
    }

    public function cerbosPrincipalAttributes(): array
    {
        return [
            'region' => $this->region,
            'department' => $this->department,
        ];
    }
}
```

Cerbos query plans can also be applied directly to Laravel query builders. The
service provider registers a `withPlan` macro for both query builders and
Eloquent builders.

```php
$rows = User::query()
    ->withPlan($plan, [
        'owner' => 'owner_id',
    ])
    ->get();
```

You can also apply a plan with the facade:

```php
use Cerbos\Sdk\Laravel\Facades\CerbosQueryPlan;
use Illuminate\Support\Facades\DB;

$query = DB::table('leave_requests');

CerbosQueryPlan::apply($query, $plan, [
    'owner' => 'owner_id',
]);

$rows = $query->get();
```

The adapter supports `KIND_ALWAYS_ALLOWED`, `KIND_ALWAYS_DENIED`, and
`KIND_CONDITIONAL` filters. Conditional filters can use `and`, `or`, `not`,
`eq`, `ne`, `lt`, `le`, `gt`, `ge`, and `in` operators against
`request.resource.attr.*` variables. Unsupported expressions throw
`UnsupportedQueryPlanExpression`.
