<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Cerbos\Sdk\Laravel\Support;

use Cerbos\Sdk\Builder\AttributeValue;

/**
 * Converts native PHP values into Cerbos SDK AttributeValue instances.
 */
class AttributeValueFactory
{
    /**
     * Convert a scalar, array, or existing AttributeValue into an AttributeValue.
     */
    public static function make(mixed $value): AttributeValue
    {
        if ($value instanceof AttributeValue) {
            return $value;
        }

        return match (true) {
            is_bool($value) => AttributeValue::boolValue($value),
            is_int($value) => AttributeValue::intValue($value),
            is_float($value) => AttributeValue::floatValue($value),
            is_array($value) => self::arrayValue($value),
            default => AttributeValue::stringValue((string) $value),
        };
    }

    /**
     * Convert an associative attribute array into Cerbos AttributeValue instances.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, AttributeValue>
     */
    public static function makeMany(array $attributes): array
    {
        $values = [];
        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $values[$key] = self::make($value);
            }
        }

        return $values;
    }

    /**
     * Convert a PHP array into either a Cerbos list value or map value.
     *
     * @param array<mixed> $value
     */
    private static function arrayValue(array $value): AttributeValue
    {
        if (array_is_list($value)) {
            return AttributeValue::listValue(array_map(fn (mixed $item): AttributeValue => self::make($item), $value));
        }

        return AttributeValue::mapValue(self::makeMany($value));
    }
}
