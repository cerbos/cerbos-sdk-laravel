<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Support;

use Cerbos\Sdk\Builder\Principal;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Builds Cerbos principals from Laravel Authenticatable users.
 */
class PrincipalFactory
{
    /**
     * Convert a Laravel user into a Cerbos SDK Principal builder.
     *
     * @param array<string, mixed> $attributes Additional attributes for this authorization request.
     */
    public function make(Authenticatable $user, array $attributes = []): Principal
    {
        $id = method_exists($user, 'cerbosPrincipalId')
            ? $user->cerbosPrincipalId()
            : (string) $user->getAuthIdentifier();

        $principal = Principal::newInstance($id);
        $roles = method_exists($user, 'cerbosPrincipalRoles') ? $user->cerbosPrincipalRoles() : [];
        if ($roles !== []) {
            $principal->withRoles(array_values(array_map('strval', $roles)));
        }

        $attributes = array_replace(
            method_exists($user, 'cerbosPrincipalAttributes') ? $user->cerbosPrincipalAttributes() : [],
            $attributes
        );
        if ($attributes !== []) {
            $principal->withAttributes(AttributeValueFactory::makeMany($attributes));
        }

        return $principal;
    }
}
