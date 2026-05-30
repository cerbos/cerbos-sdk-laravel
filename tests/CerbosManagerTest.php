<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Test;

use Cerbos\Effect\V1\Effect;
use Cerbos\Engine\V1\PlanResourcesFilter;
use Cerbos\Engine\V1\PlanResourcesFilter\Expression;
use Cerbos\Engine\V1\PlanResourcesFilter\Expression\Operand;
use Cerbos\Response\V1\CheckResourcesResponse as EngineCheckResourcesResponse;
use Cerbos\Response\V1\CheckResourcesResponse\ResultEntry;
use Cerbos\Response\V1\CheckResourcesResponse\ResultEntry\Resource;
use Cerbos\Response\V1\PlanResourcesResponse as EnginePlanResourcesResponse;
use Cerbos\Sdk\Builder\CheckResourcesRequest;
use Cerbos\Sdk\Builder\PlanResourcesRequest;
use Cerbos\Sdk\Laravel\CerbosManager;
use Cerbos\Sdk\Laravel\Concerns\HasCerbosPrincipal;
use Cerbos\Sdk\Laravel\Concerns\HasCerbosResource;
use Cerbos\Sdk\Response\V1\CheckResourcesResponse\CheckResourcesResponse;
use Cerbos\Sdk\Response\V1\PlanResourcesResponse\PlanResourcesResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

class CerbosManagerTest extends TestCase
{
    public function testBuildsAndSendsAPlanForAModelClass(): void
    {
        $client = new FakeCerbosClient();
        $manager = new CerbosManager($client);
        $user = new CerbosTestUser([
            'id' => 123,
            'roles' => 'manager,approver',
            'region' => 'EMEA',
            'department' => 'finance',
        ]);

        $manager->plan(CerbosExpense::class, null, $user)
            ->actions(['view'])
            ->send();

        $request = $client->lastPlanRequest->toPlanResourcesRequest();

        $this->assertSame(['view'], iterator_to_array($request->getActions()));
        $this->assertSame('expense', $request->getResource()->getKind());
        $this->assertSame('123', $request->getPrincipal()->getId());
        $this->assertSame(['manager', 'approver'], iterator_to_array($request->getPrincipal()->getRoles()));
        $this->assertSame('EMEA', $request->getPrincipal()->getAttr()['region']->getStringValue());
        $this->assertSame('finance', $request->getPrincipal()->getAttr()['department']->getStringValue());
    }

    public function testPlanCanAddPerRequestPrincipalAttributes(): void
    {
        $client = new FakeCerbosClient();
        $manager = new CerbosManager(
            $client,
            authResolver: fn (): CerbosTestUser => new CerbosTestUser([
                'id' => 123,
                'roles' => 'manager',
                'region' => 'EMEA',
                'department' => 'finance',
            ])
        );

        $manager->plan(CerbosExpense::class)
            ->forUser(attributes: ['region' => 'APAC', 'ipAddress' => '203.0.113.10'])
            ->actions(['view'])
            ->send();

        $principal = $client->lastPlanRequest->toPlanResourcesRequest()->getPrincipal();

        $this->assertSame('APAC', $principal->getAttr()['region']->getStringValue());
        $this->assertSame('finance', $principal->getAttr()['department']->getStringValue());
        $this->assertSame('203.0.113.10', $principal->getAttr()['ipAddress']->getStringValue());
    }

    public function testCheckCanAddPerRequestPrincipalAttributes(): void
    {
        $client = new FakeCerbosClient();
        $manager = new CerbosManager($client);
        $user = new CerbosTestUser([
            'id' => 123,
            'roles' => 'manager',
            'region' => 'EMEA',
            'department' => 'finance',
        ]);
        $expense = new CerbosExpense(['id' => 456]);

        $manager->check($expense)
            ->forUser($user, ['mfa' => true])
            ->actions(['view'])
            ->send();

        $principal = $client->lastCheckRequest->toCheckResourcesRequest()->getPrincipal();

        $this->assertTrue($principal->getAttr()['mfa']->getBoolValue());
        $this->assertSame('EMEA', $principal->getAttr()['region']->getStringValue());
    }

    public function testBuildsAndSendsACheckForAModelInstance(): void
    {
        $client = new FakeCerbosClient();
        $manager = new CerbosManager($client);
        $expense = new CerbosExpense([
            'id' => 456,
            'amount' => 125.5,
            'region' => 'APAC',
            'status' => 'PENDING',
            'owner_id' => 'owner-1',
            'vendor' => 'Acme',
        ]);
        $user = new CerbosTestUser(['id' => 123, 'roles' => 'manager']);

        $result = $manager->check($expense, null, $user)
            ->actions(['view', 'approve'])
            ->send();

        $request = $client->lastCheckRequest->toCheckResourcesRequest();
        $entry = iterator_to_array($request->getResources())[0];

        $this->assertTrue($result->isAllowed('view'));
        $this->assertFalse($result->isAllowed('approve'));
        $this->assertSame(['view', 'approve'], iterator_to_array($entry->getActions()));
        $this->assertSame('expense', $entry->getResource()->getKind());
        $this->assertSame('456', $entry->getResource()->getId());
        $this->assertSame(125.5, $entry->getResource()->getAttr()['amount']->getNumberValue());
        $this->assertSame('owner-1', $entry->getResource()->getAttr()['ownerId']->getStringValue());
    }

    public function testShortcutAuthorizationMethodsUseTheCurrentPrincipalResolver(): void
    {
        $client = new FakeCerbosClient();
        $manager = new CerbosManager(
            $client,
            authResolver: fn (): CerbosTestUser => new CerbosTestUser(['id' => 123, 'roles' => 'manager'])
        );
        $expense = new CerbosExpense(['id' => 456]);

        $this->assertTrue($manager->isAllowed('view', $expense));
        $this->assertTrue($manager->notAllowed('approve', $expense));
    }

    public function testPlanCanBeAppliedToAQueryWithTheModelColumnMap(): void
    {
        $client = new FakeCerbosClient(new PlanResourcesResponse(new EnginePlanResourcesResponse([
            'filter' => new PlanResourcesFilter([
                'kind' => PlanResourcesFilter\Kind::KIND_CONDITIONAL,
                'condition' => new Operand([
                    'expression' => new Expression([
                        'operator' => 'eq',
                        'operands' => [
                            new Operand(['variable' => 'request.resource.attr.ownerId']),
                            new Operand(['value' => new \Google\Protobuf\Value(['string_value' => 'owner-1'])]),
                        ],
                    ]),
                ]),
            ]),
        ])));
        $manager = new CerbosManager($client);
        $query = $this->query();

        $manager->plan(CerbosExpense::class, null, new CerbosTestUser(['id' => 123]))
            ->actions(['view'])
            ->applyTo($query);

        $this->assertSame('select * from "expenses" where "owner_id" = ?', $query->toSql());
        $this->assertSame(['owner-1'], $query->getBindings());
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);

        return $capsule->getConnection()->table('expenses');
    }
}

class FakeCerbosClient
{
    public ?PlanResourcesRequest $lastPlanRequest = null;
    public ?CheckResourcesRequest $lastCheckRequest = null;

    public function __construct(
        private ?PlanResourcesResponse $planResponse = null,
        private ?CheckResourcesResponse $checkResponse = null
    ) {
    }

    public function planResources(PlanResourcesRequest $request): PlanResourcesResponse
    {
        $this->lastPlanRequest = $request;

        return $this->planResponse ?? new PlanResourcesResponse(new EnginePlanResourcesResponse([
            'filter' => new PlanResourcesFilter(['kind' => PlanResourcesFilter\Kind::KIND_ALWAYS_ALLOWED]),
        ]));
    }

    public function checkResources(CheckResourcesRequest $request): CheckResourcesResponse
    {
        $this->lastCheckRequest = $request;

        return $this->checkResponse ?? new CheckResourcesResponse(new EngineCheckResourcesResponse([
            'results' => [
                new ResultEntry([
                    'resource' => new Resource(['id' => '456', 'kind' => 'expense']),
                    'actions' => [
                        'view' => Effect::EFFECT_ALLOW,
                        'approve' => Effect::EFFECT_DENY,
                    ],
                ]),
            ],
        ]));
    }
}

class CerbosExpense extends Model
{
    use HasCerbosResource;

    protected $guarded = [];

    public function cerbosResourceKind(): string
    {
        return 'expense';
    }

    public function cerbosResourceAttributes(): array
    {
        return [
            'amount' => $this->amount,
            'region' => $this->region,
            'status' => $this->status,
            'ownerId' => $this->owner_id,
            'vendor' => $this->vendor,
        ];
    }

    public static function cerbosColumnMap(): array
    {
        return ['ownerId' => 'owner_id'];
    }
}

class CerbosTestUser extends Model implements Authenticatable
{
    use HasCerbosPrincipal;

    protected $guarded = [];

    public function cerbosPrincipalRoles(): array
    {
        return array_filter(explode(',', (string) $this->roles));
    }

    public function cerbosPrincipalAttributes(): array
    {
        return [
            'region' => $this->region,
            'department' => $this->department,
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute('id');
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
