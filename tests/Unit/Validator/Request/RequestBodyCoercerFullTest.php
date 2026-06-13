<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RequestBodyCoercerFullTest extends TestCase
{
    private RequestBodyCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new RequestBodyCoercer();
    }

    #[Test]
    public function coerce_returns_value_when_disabled(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: false));

        $this->assertSame('42', $result);
    }

    #[Test]
    public function coerce_returns_value_when_null_schema(): void
    {
        $result = $this->coercer->coerce('test', new CoercionContext(schema: null, enabled: true));

        $this->assertSame('test', $result);
    }

    #[Test]
    public function coerce_nullable_null_value(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, new CoercionContext(schema: $schema, enabled: true, strict: false, nullableAsType: true));

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_to_string_from_int(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('42', $result);
    }

    #[Test]
    public function coerce_to_string_from_float(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('3.14', $result);
    }

    #[Test]
    public function coerce_to_string_from_bool(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('1', $result);
    }

    #[Test]
    public function coerce_to_integer_from_string(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_to_integer_from_float(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(3, $result);
    }

    #[Test]
    public function coerce_to_integer_from_float_strict_throws(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce(3.14, new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_to_integer_from_bool(): void
    {
        $schema = new Schema(type: 'integer');

        $this->assertSame(1, $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true)));
        $this->assertSame(0, $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true)));
    }

    #[Test]
    public function coerce_to_number_from_string(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('3.14', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_to_number_from_int(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_to_number_from_string_strict_invalid(): void
    {
        $schema = new Schema(type: 'number');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not_a_number', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_to_number_from_bool(): void
    {
        $schema = new Schema(type: 'number');

        $this->assertSame(1.0, $this->coercer->coerce(true, new CoercionContext(schema: $schema, enabled: true)));
        $this->assertSame(0.0, $this->coercer->coerce(false, new CoercionContext(schema: $schema, enabled: true)));
    }

    #[Test]
    public function coerce_to_boolean_from_string(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce('true', new CoercionContext(schema: $schema, enabled: true)));
        $this->assertFalse($this->coercer->coerce('false', new CoercionContext(schema: $schema, enabled: true)));
        $this->assertTrue($this->coercer->coerce('1', new CoercionContext(schema: $schema, enabled: true)));
        $this->assertFalse($this->coercer->coerce('0', new CoercionContext(schema: $schema, enabled: true)));
    }

    #[Test]
    public function coerce_to_boolean_from_int(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce(1, new CoercionContext(schema: $schema, enabled: true)));
        $this->assertFalse($this->coercer->coerce(0, new CoercionContext(schema: $schema, enabled: true)));
    }

    #[Test]
    public function coerce_to_boolean_from_float(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce(1.0, new CoercionContext(schema: $schema, enabled: true)));
        $this->assertFalse($this->coercer->coerce(0.0, new CoercionContext(schema: $schema, enabled: true)));
    }

    #[Test]
    public function coerce_union_type_selects_valid(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('42', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_object_with_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
            ],
        );

        $result = $this->coercer->coerce(['age' => '30'], new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame(['age' => 30], $result);
    }

    #[Test]
    public function coerce_array_with_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce(['1', '2'], new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame([1, 2], $result);
    }

    #[Test]
    public function coerce_to_integer_from_string_strict_invalid(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('not_a_number', new CoercionContext(schema: $schema, enabled: true, strict: true));
    }

    #[Test]
    public function coerce_returns_null_type_schema_unchanged(): void
    {
        $schema = new Schema();

        $result = $this->coercer->coerce('test', new CoercionContext(schema: $schema, enabled: true));

        $this->assertSame('test', $result);
    }
}
