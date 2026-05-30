<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Facades;

use Cerbos\Sdk\Laravel\QueryPlan\LaravelQueryPlanAdapter;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for applying Cerbos query plans to Laravel query builders.
 *
 * @method static \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder apply(\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query, mixed $plan, array|\Closure $columnMap = [])
 */
class CerbosQueryPlan extends Facade
{
    /**
     * Return the service container binding resolved by the facade.
     */
    protected static function getFacadeAccessor(): string
    {
        return LaravelQueryPlanAdapter::class;
    }
}
