<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\QueryPlan;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Registers query plan macros on Laravel query and Eloquent builders.
 */
class QueryPlanBuilderMacros
{
    /**
     * Register the withPlan macro on supported Laravel builders.
     */
    public static function register(LaravelQueryPlanAdapter $adapter): void
    {
        $withPlan = function (mixed $plan, array|Closure $columnMap = []) use ($adapter): QueryBuilder|EloquentBuilder {
            return $adapter->apply($this, $plan, $columnMap);
        };

        QueryBuilder::macro('withPlan', $withPlan);
        EloquentBuilder::macro('withPlan', $withPlan);
    }
}
