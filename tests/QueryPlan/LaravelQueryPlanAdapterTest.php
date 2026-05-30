<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Test\QueryPlan;

use Cerbos\Engine\V1\PlanResourcesFilter;
use Cerbos\Engine\V1\PlanResourcesFilter\Expression;
use Cerbos\Engine\V1\PlanResourcesFilter\Expression\Operand;
use Cerbos\Engine\V1\PlanResourcesFilter\Kind;
use Cerbos\Response\V1\PlanResourcesResponse;
use Cerbos\Sdk\Laravel\QueryPlan\LaravelQueryPlanAdapter;
use Cerbos\Sdk\Laravel\QueryPlan\UnsupportedQueryPlanExpression;
use Cerbos\Sdk\Laravel\Test\TestCase;
use Google\Protobuf\Value;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

class LaravelQueryPlanAdapterTest extends TestCase
{
    public function testAlwaysAllowedLeavesTheQueryUnchanged(): void
    {
        $query = $this->query();

        (new LaravelQueryPlanAdapter())->apply($query, [
            'filter' => ['kind' => 'KIND_ALWAYS_ALLOWED'],
        ]);

        $this->assertSame('select * from "leave_requests"', $query->toSql());
        $this->assertSame([], $query->getBindings());
    }

    public function testAlwaysDeniedAddsAnImpossiblePredicate(): void
    {
        $query = $this->query();

        (new LaravelQueryPlanAdapter())->apply($query, [
            'filter' => ['kind' => 'KIND_ALWAYS_DENIED'],
        ]);

        $this->assertSame('select * from "leave_requests" where 1 = 0', $query->toSql());
        $this->assertSame([], $query->getBindings());
    }

    public function testConditionalFilterAppliesComparisonsAndBooleanGroups(): void
    {
        $query = $this->query();

        (new LaravelQueryPlanAdapter())->apply($query, [
            'filter' => [
                'kind' => 'KIND_CONDITIONAL',
                'condition' => [
                    'expression' => [
                        'operator' => 'and',
                        'operands' => [
                            [
                                'expression' => [
                                    'operator' => 'eq',
                                    'operands' => [
                                        ['variable' => 'request.resource.attr.status'],
                                        ['value' => 'PENDING_APPROVAL'],
                                    ],
                                ],
                            ],
                            [
                                'expression' => [
                                    'operator' => 'or',
                                    'operands' => [
                                        [
                                            'expression' => [
                                                'operator' => 'eq',
                                                'operands' => [
                                                    ['variable' => 'request.resource.attr.department'],
                                                    ['value' => 'marketing'],
                                                ],
                                            ],
                                        ],
                                        [
                                            'expression' => [
                                                'operator' => 'ne',
                                                'operands' => [
                                                    ['variable' => 'request.resource.attr.team'],
                                                    ['value' => 'design'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'select * from "leave_requests" where ("status" = ? and ("department" = ? or "team" != ?))',
            $query->toSql()
        );
        $this->assertSame(['PENDING_APPROVAL', 'marketing', 'design'], $query->getBindings());
    }

    public function testCanMapCerbosAttributesToDatabaseColumns(): void
    {
        $query = $this->query();

        (new LaravelQueryPlanAdapter())->apply($query, [
            'filter' => [
                'kind' => 'KIND_CONDITIONAL',
                'condition' => [
                    'expression' => [
                        'operator' => 'eq',
                        'operands' => [
                            ['variable' => 'request.resource.attr.owner'],
                            ['value' => 'alice'],
                        ],
                    ],
                ],
            ],
        ], ['owner' => 'owner_id']);

        $this->assertSame('select * from "leave_requests" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['alice'], $query->getBindings());
    }

    public function testAcceptsPlanResourcesResponseObjectsFromThePhpSdk(): void
    {
        $query = $this->query();
        $plan = new PlanResourcesResponse([
            'filter' => new PlanResourcesFilter([
                'kind' => Kind::KIND_CONDITIONAL,
                'condition' => new Operand([
                    'expression' => new Expression([
                        'operator' => 'eq',
                        'operands' => [
                            new Operand(['variable' => 'request.resource.attr.owner']),
                            new Operand(['value' => new Value(['string_value' => 'alice'])]),
                        ],
                    ]),
                ]),
            ]),
        ]);

        (new LaravelQueryPlanAdapter())->apply($query, $plan, ['owner' => 'owner_id']);

        $this->assertSame('select * from "leave_requests" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['alice'], $query->getBindings());
    }

    public function testSupportsInAndNotOperators(): void
    {
        $query = $this->query();

        (new LaravelQueryPlanAdapter())->apply($query, [
            'filter' => [
                'kind' => 'KIND_CONDITIONAL',
                'condition' => [
                    'expression' => [
                        'operator' => 'and',
                        'operands' => [
                            [
                                'expression' => [
                                    'operator' => 'in',
                                    'operands' => [
                                        ['variable' => 'request.resource.attr.status'],
                                        [
                                            'expression' => [
                                                'operator' => 'list',
                                                'operands' => [
                                                    ['value' => 'PENDING_APPROVAL'],
                                                    ['value' => 'APPROVED'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'expression' => [
                                    'operator' => 'not',
                                    'operands' => [
                                        [
                                            'expression' => [
                                                'operator' => 'eq',
                                                'operands' => [
                                                    ['variable' => 'request.resource.attr.archived'],
                                                    ['value' => true],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'select * from "leave_requests" where ("status" in (?, ?) and "archived" != ?)',
            $query->toSql()
        );
        $this->assertSame(['PENDING_APPROVAL', 'APPROVED', true], $query->getBindings());
    }

    public function testRejectsUnsupportedResourceVariables(): void
    {
        $this->expectException(UnsupportedQueryPlanExpression::class);
        $this->expectExceptionMessage('Only request.resource.attr variables can be converted');

        (new LaravelQueryPlanAdapter())->apply($this->query(), [
            'filter' => [
                'kind' => 'KIND_CONDITIONAL',
                'condition' => [
                    'expression' => [
                        'operator' => 'eq',
                        'operands' => [
                            ['variable' => 'request.principal.attr.department'],
                            ['value' => 'marketing'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function query(): Builder
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);

        return $capsule->getConnection()->table('leave_requests');
    }
}
