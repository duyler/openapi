<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ResponseTypeCoercerFullTest extends TestCase
{
    private ResponseTypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ResponseTypeCoercer();
    }

    #[Test]
    public function coerce_number_from_float(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(3.14, $schema, true);

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_number_from_int(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(42, $schema, true);

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_number_from_string(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce('3.14', $schema, true);

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_number_from_bool_true(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(true, $schema, true);

        $this->assertSame(1.0, $result);
    }

    #[Test]
    public function coerce_number_from_bool_false(): void
    {
        $schema = new Schema(type: 'number');

        $result = $this->coercer->coerce(false, $schema, true);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function coerce_union_type_selects_first_valid(): void
    {
        $schema = new Schema(type: ['integer', 'string']);

        $result = $this->coercer->coerce('42', $schema, true);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_returns_value_when_disabled(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('42', $schema, false);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function coerce_returns_value_when_null_schema(): void
    {
        $result = $this->coercer->coerce('test', null, true);

        $this->assertSame('test', $result);
    }

    #[Test]
    public function coerce_nullable_null(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $result = $this->coercer->coerce(null, $schema, true, true);

        $this->assertNull($result);
    }

    #[Test]
    public function coerce_object_with_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
        );

        $result = $this->coercer->coerce(['name' => 'John', 'age' => '30'], $schema, true);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function coerce_array_with_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $result = $this->coercer->coerce(['1', '2', '3'], $schema, true);

        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function coerce_string_from_int(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->coercer->coerce(42, $schema, true);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_integer_from_string(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce('42', $schema, true);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_integer_from_float(): void
    {
        $schema = new Schema(type: 'integer');

        $result = $this->coercer->coerce(3.14, $schema, true);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function coerce_integer_from_bool(): void
    {
        $schema = new Schema(type: 'integer');

        $this->assertSame(1, $this->coercer->coerce(true, $schema, true));
        $this->assertSame(0, $this->coercer->coerce(false, $schema, true));
    }

    #[Test]
    public function coerce_boolean_from_string(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce('true', $schema, true));
        $this->assertFalse($this->coercer->coerce('false', $schema, true));
        $this->assertTrue($this->coercer->coerce('1', $schema, true));
        $this->assertFalse($this->coercer->coerce('0', $schema, true));
    }

    #[Test]
    public function coerce_boolean_from_int(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce(1, $schema, true));
        $this->assertFalse($this->coercer->coerce(0, $schema, true));
    }

    #[Test]
    public function coerce_boolean_from_float(): void
    {
        $schema = new Schema(type: 'boolean');

        $this->assertTrue($this->coercer->coerce(1.0, $schema, true));
        $this->assertFalse($this->coercer->coerce(0.0, $schema, true));
    }
}
