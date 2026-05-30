<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Concerns;

/**
 * Provides default Cerbos principal mapping methods for Laravel user models.
 */
trait HasCerbosPrincipal
{
    /**
     * Return the Cerbos principal id for this user.
     */
    public function cerbosPrincipalId(): string
    {
        return (string) $this->getAuthIdentifier();
    }

    /**
     * Return the Cerbos roles for this user.
     *
     * @return list<string>
     */
    public function cerbosPrincipalRoles(): array
    {
        $roles = $this->getAttribute('roles');

        if (is_array($roles)) {
            return array_values(array_map('strval', $roles));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $roles))));
    }

    /**
     * Return additional Cerbos principal attributes for this user.
     *
     * @return array<string, mixed>
     */
    public function cerbosPrincipalAttributes(): array
    {
        return [];
    }
}
