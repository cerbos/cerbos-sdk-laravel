<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel;

use Cerbos\Sdk\Laravel\QueryPlan\LaravelQueryPlanAdapter;
use Cerbos\Sdk\Laravel\Support\CheckBuilder;
use Cerbos\Sdk\Laravel\Support\PlanBuilder;
use Cerbos\Sdk\Laravel\Support\PrincipalFactory;
use Cerbos\Sdk\Laravel\Support\ResourceFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Coordinates Laravel-friendly Cerbos authorization builders and shared services.
 */
class CerbosManager
{
    /**
     * Create a new Cerbos manager.
     *
     * @param mixed $client Cerbos PHP SDK client or a compatible test double.
     * @param callable|null $authResolver Resolver returning the current Laravel user.
     */
    public function __construct(
        private mixed $client,
        private ?PrincipalFactory $principalFactory = null,
        private ?ResourceFactory $resourceFactory = null,
        private ?LaravelQueryPlanAdapter $queryPlanAdapter = null,
        private mixed $authResolver = null,
    ) {
        $this->principalFactory ??= new PrincipalFactory();
        $this->resourceFactory ??= new ResourceFactory();
        $this->queryPlanAdapter ??= new LaravelQueryPlanAdapter();
    }

    /**
     * Start a PlanResources request for a Laravel model class, model instance, or resource kind.
     */
    public function plan(string|Model $resource, string|int|null $resourceId = null, ?Authenticatable $principal = null): PlanBuilder
    {
        return new PlanBuilder($this, $resource, $resourceId, $principal);
    }

    /**
     * Start a CheckResources request for a Laravel model class, model instance, or resource kind.
     */
    public function check(string|Model $resource, string|int|null $resourceId = null, ?Authenticatable $principal = null): CheckBuilder
    {
        return new CheckBuilder($this, $resource, $resourceId, $principal);
    }

    /**
     * Check whether the principal is allowed to perform a single action on the resource.
     */
    public function isAllowed(string $action, string|Model $resource, ?Authenticatable $principal = null): bool
    {
        return $this->check($resource, null, $principal)->actions([$action])->send()->isAllowed($action);
    }

    /**
     * Check whether the principal is denied or missing permission for a single action on the resource.
     */
    public function notAllowed(string $action, string|Model $resource, ?Authenticatable $principal = null): bool
    {
        return ! $this->isAllowed($action, $resource, $principal);
    }

    /**
     * Return the underlying Cerbos client used to send SDK requests.
     */
    public function client(): mixed
    {
        return $this->client;
    }

    /**
     * Return the factory that converts Laravel users into Cerbos principals.
     */
    public function principalFactory(): PrincipalFactory
    {
        return $this->principalFactory;
    }

    /**
     * Return the factory that converts Laravel models into Cerbos resources.
     */
    public function resourceFactory(): ResourceFactory
    {
        return $this->resourceFactory;
    }

    /**
     * Return the adapter used to apply Cerbos query plans to Laravel builders.
     */
    public function queryPlanAdapter(): LaravelQueryPlanAdapter
    {
        return $this->queryPlanAdapter;
    }

    /**
     * Resolve the current authenticated principal, if an auth resolver was configured.
     */
    public function currentPrincipal(): ?Authenticatable
    {
        if (is_callable($this->authResolver)) {
            return ($this->authResolver)();
        }

        return null;
    }
}
