<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Concerns;

use Illuminate\Support\Str;

/**
 * Provides default Cerbos resource mapping methods for Laravel models.
 */
trait HasCerbosResource
{
    /**
     * Return the Cerbos resource kind for this model.
     */
    public function cerbosResourceKind(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * Return the Cerbos resource id for this model.
     */
    public function cerbosResourceId(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Return Cerbos resource attributes for this model.
     *
     * @return array<string, mixed>
     */
    public function cerbosResourceAttributes(): array
    {
        return [];
    }

    /**
     * Return a map of Cerbos resource attribute names to database column names.
     *
     * @return array<string, string>
     */
    public static function cerbosColumnMap(): array
    {
        return [];
    }
}
