<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TypeCoercerNestedTypesTest extends TestCase
{
    private TypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new TypeCoercer();
    }

    #[Test]
    public function coerce_object_property_to_integer(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, true);

        $this->assertSame(['limit' => 3], $result);
    }

    #[Test]
    public function coerce_object_property_to_boolean(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['enabled' => new Schema(type: 'boolean')],
            ),
        );

        $result = $this->coercer->coerce(['enabled' => 'true'], $param, true);

        $this->assertSame(['enabled' => true], $result);
    }

    #[Test]
    public function coerce_object_property_to_number(): void
    {
        $param = new Parameter(
            name: 'range',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['min' => new Schema(type: 'number')],
            ),
        );

        $result = $this->coercer->coerce(['min' => '19.99'], $param, true);

        $this->assertSame(['min' => 19.99], $result);
    }

    #[Test]
    public function coerce_array_items_to_integer(): void
    {
        $param = new Parameter(
            name: 'ids',
            in: 'query',
            schema: new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
            ),
        );

        $result = $this->coercer->coerce(['1', '2', '3'], $param, true);

        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function coerce_array_of_objects_recursively(): void
    {
        $param = new Parameter(
            name: 'points',
            in: 'query',
            schema: new Schema(
                type: 'array',
                items: new Schema(
                    type: 'object',
                    properties: ['x' => new Schema(type: 'integer')],
                ),
            ),
        );

        $result = $this->coercer->coerce([['x' => '1'], ['x' => '2']], $param, true);

        $this->assertSame([['x' => 1], ['x' => 2]], $result);
    }

    #[Test]
    public function coerce_object_property_holding_array_of_integers(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: [
                    'ids' => new Schema(type: 'array', items: new Schema(type: 'integer')),
                ],
            ),
        );

        $result = $this->coercer->coerce(['ids' => ['7', '8']], $param, true);

        $this->assertSame(['ids' => [7, 8]], $result);
    }

    #[Test]
    public function keep_object_properties_absent_from_value_untouched(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: [
                    'limit' => new Schema(type: 'integer'),
                    'offset' => new Schema(type: 'integer'),
                ],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, true);

        $this->assertSame(['limit' => 3], $result);
    }

    #[Test]
    public function coerce_declared_property_that_follows_an_absent_one(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: [
                    'offset' => new Schema(type: 'integer'),
                    'limit' => new Schema(type: 'integer'),
                ],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, true);

        $this->assertSame(['limit' => 3], $result);
    }

    #[Test]
    public function keep_undeclared_object_members_as_is(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3', 'cursor' => 'abc'], $param, true);

        $this->assertSame(['limit' => 3, 'cursor' => 'abc'], $result);
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_object_property_in_strict_mode(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce(['limit' => 'not-a-number'], $param, true, true);
    }

    #[Test]
    public function keep_invalid_object_property_as_is_in_non_strict_mode(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $result = $this->coercer->coerce(['limit' => 'not-a-number'], $param, true, false);

        $this->assertSame(['limit' => 'not-a-number'], $result);
    }

    #[Test]
    public function keep_nested_null_property_as_null_when_nullable(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer', nullable: true)],
            ),
        );

        $result = $this->coercer->coerce(['limit' => null], $param, true);

        $this->assertSame(['limit' => null], $result);
    }

    #[Test]
    public function coerce_object_property_with_union_type_to_integer(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: ['integer', 'string'])],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, true);

        $this->assertSame(['limit' => 3], $result);
    }

    #[Test]
    public function coerce_object_properties_when_declared_by_union_type(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: ['object', 'string'],
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, true);

        $this->assertSame(['limit' => 3], $result);
    }

    #[Test]
    public function skip_coercion_of_nested_values_when_coercion_disabled(): void
    {
        $param = new Parameter(
            name: 'page',
            in: 'query',
            schema: new Schema(
                type: 'object',
                properties: ['limit' => new Schema(type: 'integer')],
            ),
        );

        $result = $this->coercer->coerce(['limit' => '3'], $param, false);

        $this->assertSame(['limit' => '3'], $result);
    }
}
