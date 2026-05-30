<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Test\QueryPlan;

use Cerbos\Sdk\Laravel\Facades\CerbosQueryPlan;
use Cerbos\Sdk\Laravel\QueryPlan\LaravelQueryPlanAdapter;
use Cerbos\Sdk\Laravel\QueryPlan\QueryPlanBuilderMacros;
use Cerbos\Sdk\Laravel\Test\TestCase;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

class QueryPlanIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    public function testEloquentBuildersCanApplyPlansWithAMacro(): void
    {
        QueryPlanBuilderMacros::register(new LaravelQueryPlanAdapter());

        $query = QueryPlanUser::query()->withPlan($this->plan(), ['owner' => 'owner_id']);

        $this->assertSame('select * from "leave_requests" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['alice'], $query->getBindings());
    }

    public function testQueryBuildersCanApplyPlansWithAMacro(): void
    {
        QueryPlanBuilderMacros::register(new LaravelQueryPlanAdapter());

        $query = Capsule::table('leave_requests')->withPlan($this->plan(), ['owner' => 'owner_id']);

        $this->assertSame('select * from "leave_requests" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['alice'], $query->getBindings());
    }

    public function testFacadeCanApplyAPlanToABuilder(): void
    {
        $container = new Container();
        $container->instance(LaravelQueryPlanAdapter::class, new LaravelQueryPlanAdapter());
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $query = Capsule::table('leave_requests');

        CerbosQueryPlan::apply($query, $this->plan(), ['owner' => 'owner_id']);

        $this->assertSame('select * from "leave_requests" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['alice'], $query->getBindings());
    }

    private function plan(): array
    {
        return [
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
        ];
    }
}

class QueryPlanUser extends Model
{
    protected $table = 'leave_requests';
}
