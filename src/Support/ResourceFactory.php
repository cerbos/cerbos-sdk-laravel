<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Support;

use Cerbos\Sdk\Builder\Resource;
use Cerbos\Sdk\Builder\ResourceEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds Cerbos resources and resource entries from Laravel models or resource kinds.
 */
class ResourceFactory
{
    /**
     * Create a Cerbos resource for query planning.
     *
     * @param array<string, mixed> $attributes
     */
    public function planResource(string|Model $resource, string|int|null $resourceId = null, array $attributes = []): Resource
    {
        $kind = $this->kind($resource);
        $resourceId = (string) ($resourceId ?? ($resource instanceof Model ? $this->id($resource) : ''));

        return Resource::newInstance($kind, $resourceId)
            ->withAttributes(AttributeValueFactory::makeMany($attributes));
    }

    /**
     * Create a Cerbos resource entry for resource checks.
     *
     * @param array<string, mixed> $attributes
     * @param list<string> $actions
     */
    public function resourceEntry(string|Model $resource, string|int|null $resourceId = null, array $attributes = [], array $actions = []): ResourceEntry
    {
        if ($resource instanceof Model) {
            $attributes = array_replace($this->attributes($resource), $attributes);
        }

        return ResourceEntry::newInstance($this->kind($resource), (string) ($resourceId ?? $this->id($resource)))
            ->withAttributes(AttributeValueFactory::makeMany($attributes))
            ->withActions($actions);
    }

    /**
     * Resolve the Cerbos resource kind from a model instance, model class, or raw kind string.
     */
    public function kind(string|Model $resource): string
    {
        if ($resource instanceof Model && method_exists($resource, 'cerbosResourceKind')) {
            return $resource->cerbosResourceKind();
        }

        if (is_string($resource) && is_subclass_of($resource, Model::class)) {
            $model = new $resource();
            if (method_exists($model, 'cerbosResourceKind')) {
                return $model->cerbosResourceKind();
            }

            return Str::snake(class_basename($resource));
        }

        if (is_string($resource)) {
            return $resource;
        }

        throw new InvalidArgumentException('Cerbos resource must be a model instance, model class, or resource kind string.');
    }

    /**
     * Resolve the Cerbos attribute to database column map for a model class or instance.
     *
     * @return array<string, string>
     */
    public function columnMap(string|Model $resource): array
    {
        $class = $resource instanceof Model ? $resource::class : $resource;

        return is_string($class) && method_exists($class, 'cerbosColumnMap') ? $class::cerbosColumnMap() : [];
    }

    /**
     * Resolve a resource id from a model instance or fail when no id was provided.
     */
    private function id(string|Model $resource): string
    {
        if ($resource instanceof Model) {
            if (method_exists($resource, 'cerbosResourceId')) {
                return $resource->cerbosResourceId();
            }

            return (string) $resource->getKey();
        }

        throw new InvalidArgumentException('A resource id is required when the Cerbos resource is not a model instance.');
    }

    /**
     * Resolve default Cerbos resource attributes from a model instance.
     *
     * @return array<string, mixed>
     */
    private function attributes(Model $resource): array
    {
        return method_exists($resource, 'cerbosResourceAttributes') ? $resource->cerbosResourceAttributes() : [];
    }
}
