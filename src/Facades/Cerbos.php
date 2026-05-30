<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Facades;

use Cerbos\Sdk\Laravel\CerbosManager;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Laravel-friendly Cerbos authorization manager.
 *
 * @method static \Cerbos\Sdk\Laravel\Support\PlanBuilder plan(string|\Illuminate\Database\Eloquent\Model $resource, string|int|null $resourceId = null, ?\Illuminate\Contracts\Auth\Authenticatable $principal = null)
 * @method static \Cerbos\Sdk\Laravel\Support\CheckBuilder check(string|\Illuminate\Database\Eloquent\Model $resource, string|int|null $resourceId = null, ?\Illuminate\Contracts\Auth\Authenticatable $principal = null)
 * @method static bool isAllowed(string $action, string|\Illuminate\Database\Eloquent\Model $resource, ?\Illuminate\Contracts\Auth\Authenticatable $principal = null)
 * @method static bool notAllowed(string $action, string|\Illuminate\Database\Eloquent\Model $resource, ?\Illuminate\Contracts\Auth\Authenticatable $principal = null)
 */
class Cerbos extends Facade
{
    /**
     * Return the service container binding resolved by the facade.
     */
    protected static function getFacadeAccessor(): string
    {
        return CerbosManager::class;
    }
}
