<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\QueryPlan;

use Cerbos\Engine\V1\PlanResourcesFilter\Kind;
use Closure;
use Google\Protobuf\Value;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Applies Cerbos PlanResources filters to Laravel query builders.
 */
class LaravelQueryPlanAdapter
{
    private const RESOURCE_ATTR_PREFIX = 'request.resource.attr.';

    /**
     * Append the Cerbos query plan filter to the given Laravel builder.
     *
     * @param array<string, string>|Closure(string): string $columnMap
     * @return QueryBuilder|EloquentBuilder
     */
    public function apply(QueryBuilder|EloquentBuilder $query, mixed $plan, array|Closure $columnMap = []): QueryBuilder|EloquentBuilder
    {
        $filter = $this->field($plan, 'filter');
        if ($filter === null) {
            throw new InvalidArgumentException('Cerbos plan response does not contain a filter.');
        }

        return match ($this->filterKind($filter)) {
            'KIND_ALWAYS_ALLOWED' => $query,
            'KIND_ALWAYS_DENIED' => $query->whereRaw('1 = 0'),
            'KIND_CONDITIONAL' => $this->applyConditional($query, $filter, $columnMap),
            default => throw new UnsupportedQueryPlanExpression('Unsupported Cerbos query plan filter kind.'),
        };
    }

    /**
     * Apply a conditional Cerbos filter expression.
     *
     * @param array<string, string>|Closure(string): string $columnMap
     * @return QueryBuilder|EloquentBuilder
     */
    private function applyConditional(QueryBuilder|EloquentBuilder $query, mixed $filter, array|Closure $columnMap): QueryBuilder|EloquentBuilder
    {
        $condition = $this->field($filter, 'condition');
        if ($condition === null) {
            throw new UnsupportedQueryPlanExpression('Conditional Cerbos query plan filter is missing a condition.');
        }

        $this->applyNode($query, $condition, 'and', false, $columnMap);

        return $query;
    }

    /**
     * Apply a single Cerbos expression node to the query.
     *
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function applyNode(QueryBuilder|EloquentBuilder $query, mixed $node, string $boolean, bool $negated, array|Closure $columnMap): void
    {
        $expression = $this->expressionFromNode($node);
        $operator = strtolower((string) $this->field($expression, 'operator'));
        $operands = $this->operands($expression);

        match ($operator) {
            'and' => $this->applyBooleanGroup($query, $operands, 'and', $boolean, $negated, $columnMap),
            'or' => $this->applyBooleanGroup($query, $operands, 'or', $boolean, $negated, $columnMap),
            'not' => $this->applyNot($query, $operands, $boolean, $negated, $columnMap),
            'eq', 'ne', 'lt', 'le', 'gt', 'ge' => $this->applyComparison($query, $operator, $operands, $boolean, $negated, $columnMap),
            'in' => $this->applyIn($query, $operands, $boolean, $negated, $columnMap),
            default => throw new UnsupportedQueryPlanExpression(sprintf('Unsupported Cerbos query plan operator "%s".', $operator)),
        };
    }

    /**
     * Apply an and/or expression as a nested where group.
     *
     * @param list<mixed> $operands
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function applyBooleanGroup(QueryBuilder|EloquentBuilder $query, array $operands, string $operator, string $boolean, bool $negated, array|Closure $columnMap): void
    {
        if ($operands === []) {
            throw new UnsupportedQueryPlanExpression(sprintf('Cerbos "%s" operator requires at least one operand.', $operator));
        }

        $method = $negated ? 'whereNot' : 'where';
        $query->{$method}(function (QueryBuilder|EloquentBuilder $nested) use ($operands, $operator, $columnMap): void {
            foreach ($operands as $index => $operand) {
                $this->applyNode($nested, $operand, $index === 0 ? 'and' : $operator, false, $columnMap);
            }
        }, null, null, $boolean);
    }

    /**
     * Apply a not expression by toggling the negation state.
     *
     * @param list<mixed> $operands
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function applyNot(QueryBuilder|EloquentBuilder $query, array $operands, string $boolean, bool $negated, array|Closure $columnMap): void
    {
        if (count($operands) !== 1) {
            throw new UnsupportedQueryPlanExpression('Cerbos "not" operator requires exactly one operand.');
        }

        $this->applyNode($query, $operands[0], $boolean, ! $negated, $columnMap);
    }

    /**
     * Apply a binary comparison expression to the query.
     *
     * @param list<mixed> $operands
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function applyComparison(QueryBuilder|EloquentBuilder $query, string $operator, array $operands, string $boolean, bool $negated, array|Closure $columnMap): void
    {
        if (count($operands) !== 2) {
            throw new UnsupportedQueryPlanExpression(sprintf('Cerbos "%s" operator requires exactly two operands.', $operator));
        }

        [$left, $right] = $operands;
        $flipped = false;
        if (! $this->isVariableOperand($left) && $this->isVariableOperand($right)) {
            [$left, $right] = [$right, $left];
            $flipped = true;
        }

        $column = $this->columnFromVariable($this->variableFromOperand($left), $columnMap);
        $sqlOperator = $this->sqlOperator($operator, $negated, $flipped);
        $value = $this->valueFromOperand($right);

        if ($value === null && in_array($sqlOperator, ['=', '!='], true)) {
            $query->whereNull($column, $boolean, $sqlOperator === '!=');

            return;
        }

        $query->where($column, $sqlOperator, $value, $boolean);
    }

    /**
     * Apply an in expression to the query.
     *
     * @param list<mixed> $operands
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function applyIn(QueryBuilder|EloquentBuilder $query, array $operands, string $boolean, bool $negated, array|Closure $columnMap): void
    {
        if (count($operands) !== 2 || ! $this->isVariableOperand($operands[0])) {
            throw new UnsupportedQueryPlanExpression('Cerbos "in" operator requires a resource attribute variable followed by a list value.');
        }

        $column = $this->columnFromVariable($this->variableFromOperand($operands[0]), $columnMap);
        $values = $this->listFromOperand($operands[1]);

        $query->whereIn($column, $values, $boolean, $negated);
    }

    /**
     * Extract the expression payload from an operand or expression node.
     */
    private function expressionFromNode(mixed $node): mixed
    {
        if ($this->field($node, 'expression') !== null) {
            return $this->field($node, 'expression');
        }

        if ($this->field($node, 'operator') !== null) {
            return $node;
        }

        throw new UnsupportedQueryPlanExpression('Cerbos query plan operand does not contain an expression.');
    }

    /**
     * Return the operands from an expression as a plain PHP list.
     *
     * @return list<mixed>
     */
    private function operands(mixed $expression): array
    {
        $operands = $this->field($expression, 'operands') ?? [];

        return is_array($operands) ? array_values($operands) : iterator_to_array($operands, false);
    }

    /**
     * Resolve a Cerbos filter kind name from array or protobuf filter data.
     */
    private function filterKind(mixed $filter): string
    {
        $kind = $this->field($filter, 'kind');

        if (is_int($kind)) {
            return Kind::name($kind);
        }

        return (string) $kind;
    }

    /**
     * Determine whether an operand is a Cerbos variable reference.
     */
    private function isVariableOperand(mixed $operand): bool
    {
        return $this->field($operand, 'variable') !== null;
    }

    /**
     * Return the variable name from a Cerbos operand.
     */
    private function variableFromOperand(mixed $operand): string
    {
        $variable = $this->field($operand, 'variable');
        if (! is_string($variable)) {
            throw new UnsupportedQueryPlanExpression('Cerbos operand is not a variable.');
        }

        return $variable;
    }

    /**
     * Convert a Cerbos resource attribute variable into a database column.
     *
     * @param array<string, string>|Closure(string): string $columnMap
     */
    private function columnFromVariable(string $variable, array|Closure $columnMap): string
    {
        if (! str_starts_with($variable, self::RESOURCE_ATTR_PREFIX)) {
            throw new UnsupportedQueryPlanExpression('Only request.resource.attr variables can be converted to Laravel query builder columns.');
        }

        $attribute = substr($variable, strlen(self::RESOURCE_ATTR_PREFIX));

        return $columnMap instanceof Closure ? $columnMap($attribute) : ($columnMap[$attribute] ?? $attribute);
    }

    /**
     * Return a literal PHP value from a Cerbos operand.
     */
    private function valueFromOperand(mixed $operand): mixed
    {
        if (! $this->hasField($operand, 'value')) {
            throw new UnsupportedQueryPlanExpression('Cerbos operand is not a literal value.');
        }

        $value = $this->field($operand, 'value');

        return $value instanceof Value ? $this->valueFromProtobuf($value) : $value;
    }

    /**
     * Return a PHP list from a Cerbos list operand.
     *
     * @return list<mixed>
     */
    private function listFromOperand(mixed $operand): array
    {
        if ($this->field($operand, 'expression') !== null) {
            $expression = $this->field($operand, 'expression');
            if (strtolower((string) $this->field($expression, 'operator')) !== 'list') {
                throw new UnsupportedQueryPlanExpression('Cerbos "in" operator only supports list expressions as the right operand.');
            }

            return array_map(fn (mixed $item): mixed => $this->valueFromOperand($item), $this->operands($expression));
        }

        $value = $this->valueFromOperand($operand);
        if (is_array($value)) {
            return array_values($value);
        }

        throw new UnsupportedQueryPlanExpression('Cerbos "in" operator only supports list values.');
    }

    /**
     * Convert a protobuf Value into the corresponding native PHP value.
     */
    private function valueFromProtobuf(Value $value): mixed
    {
        return match ($value->getKind()) {
            'null_value' => null,
            'number_value' => $value->getNumberValue(),
            'string_value' => $value->getStringValue(),
            'bool_value' => $value->getBoolValue(),
            'list_value' => array_map(
                fn (Value $item): mixed => $this->valueFromProtobuf($item),
                iterator_to_array($value->getListValue()?->getValues() ?? [], false)
            ),
            default => throw new UnsupportedQueryPlanExpression('Unsupported protobuf value in Cerbos query plan.'),
        };
    }

    /**
     * Convert a Cerbos comparison operator into a SQL comparison operator.
     */
    private function sqlOperator(string $operator, bool $negated, bool $flipped): string
    {
        $operators = [
            'eq' => '=',
            'ne' => '!=',
            'lt' => '<',
            'le' => '<=',
            'gt' => '>',
            'ge' => '>=',
        ];

        $sqlOperator = $operators[$operator];
        if ($flipped) {
            $sqlOperator = [
                '<' => '>',
                '<=' => '>=',
                '>' => '<',
                '>=' => '<=',
            ][$sqlOperator] ?? $sqlOperator;
        }

        if (! $negated) {
            return $sqlOperator;
        }

        return [
            '=' => '!=',
            '!=' => '=',
            '<' => '>=',
            '<=' => '>',
            '>' => '<=',
            '>=' => '<',
        ][$sqlOperator];
    }

    /**
     * Check whether an array or protobuf object contains a populated field.
     */
    private function hasField(mixed $source, string $name): bool
    {
        if (is_array($source)) {
            return array_key_exists($name, $source);
        }

        $hasMethod = 'has' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (is_object($source) && method_exists($source, $hasMethod)) {
            return $source->{$hasMethod}();
        }

        return $this->field($source, $name) !== null;
    }

    /**
     * Read a field from an array or protobuf-style getter object.
     */
    private function field(mixed $source, string $name): mixed
    {
        if (is_array($source)) {
            return $source[$name] ?? null;
        }

        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (is_object($source) && method_exists($source, $method)) {
            return $source->{$method}();
        }

        return null;
    }
}
