<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Support;

use Cerbos\Sdk\Builder\CheckResourcesRequest;
use Cerbos\Sdk\Laravel\CerbosManager;
use Cerbos\Sdk\Response\V1\CheckResourcesResponse\ResultEntry\ResultEntry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Fluent builder for constructing and sending Cerbos CheckResources requests.
 */
class CheckBuilder
{
    private ?Authenticatable $principal;
    private array $principalAttributes = [];
    private array $actions = [];
    private array $resourceAttributes = [];

    /**
     * Create a check builder for the given resource target.
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
     * Set the principal for this check, defaulting to the manager's current user.
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
     * Set an explicit principal for this check.
     *
     * @param array<string, mixed> $attributes Additional principal attributes for this request.
     */
    public function withPrincipal(Authenticatable $principal, array $attributes = []): self
    {
        return $this->forUser($principal, $attributes);
    }

    /**
     * Set or merge resource id and attributes for this check.
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
     * Set the Cerbos actions to check.
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
     * Send the built CheckResources request and return the result for this resource.
     */
    public function send(): ResultEntry
    {
        $response = $this->manager->client()->checkResources($this->toRequest());

        return $response->find($this->resourceId());
    }

    /**
     * Convert this fluent builder into a Cerbos PHP SDK CheckResourcesRequest.
     */
    public function toRequest(): CheckResourcesRequest
    {
        $principal = $this->principal ?? $this->manager->currentPrincipal();
        if ($principal === null) {
            throw new RuntimeException('A Cerbos principal is required. Call forUser(), withPrincipal(), or configure an auth resolver.');
        }

        return CheckResourcesRequest::newInstance()
            ->withPrincipal($this->manager->principalFactory()->make($principal, $this->principalAttributes))
            ->withResourceEntry(
                $this->manager->resourceFactory()->resourceEntry($this->resource, $this->resourceId, $this->resourceAttributes, $this->actions)
            );
    }

    /**
     * Resolve the resource id used to find the matching result entry.
     */
    private function resourceId(): string
    {
        if ($this->resource instanceof Model && $this->resourceId === null) {
            return method_exists($this->resource, 'cerbosResourceId')
                ? $this->resource->cerbosResourceId()
                : (string) $this->resource->getKey();
        }

        return (string) $this->resourceId;
    }
}
