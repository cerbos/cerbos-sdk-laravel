<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\QueryPlan;

use LogicException;

/**
 * Raised when a Cerbos query plan expression cannot be represented as SQL.
 */
class UnsupportedQueryPlanExpression extends LogicException
{
}
