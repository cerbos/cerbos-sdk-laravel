<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Support;

use Cerbos\Sdk\Builder\PlanResourcesRequest;
use Cerbos\Sdk\Laravel\CerbosManager;
use Cerbos\Sdk\Response\V1\PlanResourcesResponse\PlanResourcesResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuntimeException;

/**
 * Fluent builder for constructing and sending Cerbos PlanResources requests.
 */
class PlanBuilder
{
    private ?Authenticatable $principal;
    private array $principalAttributes = [];
    private array $actions = [];
    private array $resourceAttributes = [];

    /**
     * Create a plan builder for the given resource target.
     */
    public function __construct(
        private CerbosManager $manager,
        private string|Model $resource,
        private string|int|null $resourceId = null,
        ?Authenticatable $principal = null,
    ) {
        $this->principal = $principal;
    }

    /**
     * Set the principal for this plan, defaulting to the manager's current user.
     *
     * @param array<string, mixed> $attributes Additional principal attributes for this request.
     */
    public function forUser(?Authenticatable $principal = null, array $attributes = []): self
    {
        $this->principal = $principal ?? $this->manager->currentPrincipal();
        $this->principalAttributes = array_replace($this->principalAttributes, $attributes);

        return $this;
    }

    /**
     * Set an explicit principal for this plan.
     *
     * @param array<string, mixed> $attributes Additional principal attributes for this request.
     */
    public function withPrincipal(Authenticatable $principal, array $attributes = []): self
    {
        return $this->forUser($principal, $attributes);
    }

    /**
     * Set or merge resource id and attributes for this plan.
     *
     * @param array<string, mixed> $attributes
     */
    public function withResource(string|int|null $resourceId = null, array $attributes = []): self
    {
        $this->resourceId = $resourceId ?? $this->resourceId;
        $this->resourceAttributes = array_replace($this->resourceAttributes, $attributes);

        return $this;
    }

    /**
     * Set the Cerbos actions to plan for.
     *
     * @param list<string>|string $actions
     */
    public function actions(array|string $actions): self
    {
        $this->actions = is_array($actions) ? array_values($actions) : [$actions];

        return $this;
    }

    /**
     * Alias for actions() for parity with the underlying Cerbos SDK builders.
     *
     * @param list<string>|string $actions
     */
    public function withActions(array|string $actions): self
    {
        return $this->actions($actions);
    }

    /**
     * Send the built PlanResources request to Cerbos.
     */
    public function send(): PlanResourcesResponse
    {
        return $this->manager->client()->planResources($this->toRequest());
    }

    /**
     * Send the plan and apply its filter to the given Laravel query builder.
     */
    public function applyTo(QueryBuilder|EloquentBuilder $query): QueryBuilder|EloquentBuilder
    {
        return $this->manager->queryPlanAdapter()->apply(
            $query,
            $this->send(),
            $this->manager->resourceFactory()->columnMap($this->resource)
        );
    }

    /**
     * Convert this fluent builder into a Cerbos PHP SDK PlanResourcesRequest.
     */
    public function toRequest(): PlanResourcesRequest
    {
        $principal = $this->principal ?? $this->manager->currentPrincipal();
        if ($principal === null) {
            throw new RuntimeException('A Cerbos principal is required. Call forUser(), withPrincipal(), or configure an auth resolver.');
        }

        return PlanResourcesRequest::newInstance()
            ->withPrincipal($this->manager->principalFactory()->make($principal, $this->principalAttributes))
            ->withResource($this->manager->resourceFactory()->planResource($this->resource, $this->resourceId, $this->resourceAttributes))
            ->withActions($this->actions);
    }
}
