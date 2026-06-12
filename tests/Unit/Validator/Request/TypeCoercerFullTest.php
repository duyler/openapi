<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/** @internal */
final class TypeCoercerFullTest extends TestCase
{
    private TypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new TypeCoercer();
    }

    #[Test]
    public function coerce_with_union_type_selects_first_valid(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
            schema: new Schema(type: ['integer', 'string']),
        );

        $result = $this->coercer->coerce('42', $param, true);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_with_union_type_falls_back_to_normalized(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
            schema: new Schema(type: ['null']),
        );

        $result = $this->coercer->coerce('hello', $param, true);

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function coerce_with_null_and_nullable_schema(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
            schema: new Schema(type: 'string', nullable: true),
        );

        $result = $this->coercer->coerce(null, $param, true);

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_null_with_null_in_types(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
            schema: new Schema(type: ['null', 'string']),
        );

        $result = $this->coercer->coerce(null, $param, true);

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_null_throws_for_non_nullable(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
            schema: new Schema(type: 'string'),
        );

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce(null, $param, true);
    }

    #[Test]
    public function coerce_normalizes_object_to_array(): void
    {
        $param = new Parameter(
            name: 'value',
            in: 'query',
        );

        $obj = new stdClass();
        $obj->name = 'test';

        $result = $this->coercer->coerce($obj, $param, false);

        $this->assertSame(['name' => 'test'], $result);
    }

    #[Test]
    public function coerce_to_integer_from_string_strict_invalid(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'query',
            schema: new Schema(type: 'integer'),
        );

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not_a_number', $param, true, true);
    }

    #[Test]
    public function coerce_to_number_from_string_strict_invalid(): void
    {
        $param = new Parameter(
            name: 'price',
            in: 'query',
            schema: new Schema(type: 'number'),
        );

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not_a_number', $param, true, true);
    }

    #[Test]
    public function coerce_to_integer_from_string_strict_valid(): void
    {
        $param = new Parameter(
            name: 'id',
            in: 'query',
            schema: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce('42', $param, true, true);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_to_number_from_string(): void
    {
        $param = new Parameter(
            name: 'price',
            in: 'query',
            schema: new Schema(type: 'number'),
        );

        $result = $this->coercer->coerce('3.14', $param, true);

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_to_boolean_true_values(): void
    {
        $param = new Parameter(
            name: 'flag',
            in: 'query',
            schema: new Schema(type: 'boolean'),
        );

        $this->assertTrue($this->coercer->coerce('true', $param, true));
        $this->assertTrue($this->coercer->coerce('1', $param, true));
        $this->assertTrue($this->coercer->coerce('yes', $param, true));
        $this->assertTrue($this->coercer->coerce('on', $param, true));
    }

    #[Test]
    public function coerce_to_boolean_false_values(): void
    {
        $param = new Parameter(
            name: 'flag',
            in: 'query',
            schema: new Schema(type: 'boolean'),
        );

        $this->assertFalse($this->coercer->coerce('false', $param, true));
        $this->assertFalse($this->coercer->coerce('0', $param, true));
        $this->assertFalse($this->coercer->coerce('no', $param, true));
        $this->assertFalse($this->coercer->coerce('off', $param, true));
    }
}
