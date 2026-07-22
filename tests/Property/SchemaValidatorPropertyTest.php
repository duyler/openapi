<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Property;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;
use function sprintf;

use const INF;
use const NAN;

#[CoversClass(JsonEquals::class)]
final class SchemaValidatorPropertyTest extends TestCase
{
    use TestTrait;

    private const string INTEGER_SCHEMA_JSON = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Property test", "version": "1.0.0"},
    "paths": {},
    "components": {
        "schemas": {
            "IntegerValue": {
                "type": "object",
                "required": ["x"],
                "properties": {
                    "x": {"type": "integer"}
                }
            }
        }
    }
}
JSON;

    private const string INTEGER_RANGE_SCHEMA_JSON = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Property test range", "version": "1.0.0"},
    "paths": {},
    "components": {
        "schemas": {
            "BoundedValue": {
                "type": "object",
                "required": ["x"],
                "properties": {
                    "x": {"type": "integer", "minimum": 10, "maximum": 20}
                }
            }
        }
    }
}
JSON;

    private const string STRING_SCHEMA_JSON = <<<'JSON'
{
    "openapi": "3.2.0",
    "info": {"title": "Property test string", "version": "1.0.0"},
    "paths": {},
    "components": {
        "schemas": {
            "StringValue": {
                "type": "object",
                "required": ["x"],
                "properties": {
                    "x": {"type": "string"}
                }
            }
        }
    }
}
JSON;

    #[Test]
    public function type_integer_rejects_non_numeric_strings_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::INTEGER_SCHEMA_JSON)
            ->build();

        $this->limitTo(100)
            ->forAll(Generator\vector(10, Generator\charPrintableAscii()))
            ->then(function (array $chars) use ($validator): void {
                $string = implode('', $chars);

                if (is_numeric($string)) {
                    return;
                }

                $threw = false;

                try {
                    $validator->validateSchema(['x' => $string], '#/components/schemas/IntegerValue');
                } catch (ValidationException) {
                    $threw = true;
                }

                $this->assertTrue($threw, sprintf('Expected non-numeric string to be rejected by type: integer schema'));
            });
    }

    #[Test]
    public function type_integer_accepts_integer_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::INTEGER_SCHEMA_JSON)
            ->build();

        $this->limitTo(100)
            ->forAll(Generator\choose(-1000, 1000))
            ->then(function (int $n) use ($validator): void {
                $validator->validateSchema(['x' => $n], '#/components/schemas/IntegerValue');

                $this->addToAssertionCount(1);
            });
    }

    #[Test]
    public function type_integer_accepts_whole_float_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::INTEGER_SCHEMA_JSON)
            ->build();

        $this->limitTo(100)
            ->forAll(Generator\choose(-1000, 1000))
            ->then(function (int $n) use ($validator): void {
                $validator->validateSchema(['x' => (float) $n], '#/components/schemas/IntegerValue');

                $this->addToAssertionCount(1);
            });
    }

    #[Test]
    public function type_integer_rejects_nan_and_inf_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::INTEGER_SCHEMA_JSON)
            ->build();

        $this->limitTo(50)
            ->forAll(Generator\elements(NAN, INF, -INF))
            ->then(function (float $value) use ($validator): void {
                $threw = false;

                try {
                    $validator->validateSchema(['x' => $value], '#/components/schemas/IntegerValue');
                } catch (ValidationException) {
                    $threw = true;
                }

                $this->assertTrue($threw, 'Expected NAN/INF/-INF to be rejected by type: integer schema');
            });
    }

    #[Test]
    public function min_max_constraints_monotonic_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::INTEGER_RANGE_SCHEMA_JSON)
            ->build();

        $this->limitTo(100)
            ->forAll(Generator\choose(-50, 80))
            ->then(function (int $n) use ($validator): void {
                $threw = false;

                try {
                    $validator->validateSchema(['x' => $n], '#/components/schemas/BoundedValue');
                } catch (ValidationException) {
                    $threw = true;
                }

                $within = $n >= 10 && $n <= 20;

                $this->assertSame(
                    false === $threw,
                    $within,
                    sprintf('Value %d (within=%s) — monotonicity violated', $n, $within ? 'true' : 'false'),
                );
            });
    }

    #[Test]
    public function json_equals_is_idempotent_property(): void
    {
        $scalarGenerator = Generator\oneOf(
            Generator\choose(-10000, 10000),
            Generator\float(),
            Generator\vector(8, Generator\charPrintableAscii()),
            Generator\bool(),
            Generator\constant(null),
        );

        $this->limitTo(100)
            ->forAll($scalarGenerator, $scalarGenerator)
            ->then(function (mixed $a, mixed $b): void {
                if (is_array($a)) {
                    $a = implode('', $a);
                }
                if (is_array($b)) {
                    $b = implode('', $b);
                }

                $first = JsonEquals::equals($a, $b);
                $second = JsonEquals::equals($a, $b);

                $this->assertSame($first, $second);
            });
    }

    #[Test]
    public function json_equals_is_symmetric_property(): void
    {
        $scalarGenerator = Generator\oneOf(
            Generator\choose(-10000, 10000),
            Generator\float(),
            Generator\vector(8, Generator\charPrintableAscii()),
            Generator\bool(),
            Generator\constant(null),
        );

        $this->limitTo(100)
            ->forAll($scalarGenerator, $scalarGenerator)
            ->then(function (mixed $a, mixed $b): void {
                if (is_array($a)) {
                    $a = implode('', $a);
                }
                if (is_array($b)) {
                    $b = implode('', $b);
                }

                $forward = JsonEquals::equals($a, $b);
                $backward = JsonEquals::equals($b, $a);

                $this->assertSame($forward, $backward);
            });
    }

    #[Test]
    public function type_string_accepts_ascii_strings_property(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::STRING_SCHEMA_JSON)
            ->build();

        $this->limitTo(100)
            ->forAll(Generator\vector(15, Generator\charPrintableAscii()))
            ->then(function (array $chars) use ($validator): void {
                $string = implode('', $chars);

                $validator->validateSchema(['x' => $string], '#/components/schemas/StringValue');

                $this->addToAssertionCount(1);
            });
    }
}
