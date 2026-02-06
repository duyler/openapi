<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Trait;

use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LengthValidationTraitTest extends TestCase
{
    #[Test]
    public function throw_min_error_when_value_less_than_min(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 2,
                    min: 5,
                    max: null,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $this->expectException(MinItemsError::class);
        $this->expectExceptionMessage('Array has 2 items, but minimum is 5 at /test');

        $tester->testValidateLength();
    }

    #[Test]
    public function throw_max_error_when_value_greater_than_max(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 10,
                    min: null,
                    max: 5,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $this->expectException(MaxItemsError::class);
        $this->expectExceptionMessage('Array has 10 items, but maximum is 5 at /test');

        $tester->testValidateLength();
    }

    #[Test]
    public function not_throw_error_when_value_in_range(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 5,
                    min: 3,
                    max: 10,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $tester->testValidateLength();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function not_throw_error_when_min_and_max_are_null(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 100,
                    min: null,
                    max: null,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $tester->testValidateLength();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_boundary_value_equals_to_min(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 5,
                    min: 5,
                    max: 10,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $tester->testValidateLength();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_boundary_value_equals_to_max(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 10,
                    min: 5,
                    max: 10,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $tester->testValidateLength();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_error_with_correct_parameters(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 3,
                    min: 7,
                    max: null,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/data/path', '/schema/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/test', '/max'),
                );
            }
        };

        $this->expectException(MinItemsError::class);

        try {
            $tester->testValidateLength();
        } catch (MinItemsError $e) {
            $this->assertSame(7, $e->params()['minItems']);
            $this->assertSame(3, $e->params()['actual']);
            $this->assertSame('/data/path', $e->dataPath());
            $this->assertSame('/schema/min', $e->schemaPath());
            throw $e;
        }
    }

    #[Test]
    public function throw_max_error_with_correct_parameters(): void
    {
        $tester = new class {
            use LengthValidationTrait;

            public function testValidateLength(): void
            {
                $this->validateLength(
                    actual: 15,
                    min: null,
                    max: 10,
                    minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, '/test', '/min'),
                    maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, '/another/path', '/schema/max'),
                );
            }
        };

        $this->expectException(MaxItemsError::class);

        try {
            $tester->testValidateLength();
        } catch (MaxItemsError $e) {
            $this->assertSame(10, $e->params()['maxItems']);
            $this->assertSame(15, $e->params()['actual']);
            $this->assertSame('/another/path', $e->dataPath());
            $this->assertSame('/schema/max', $e->schemaPath());
            throw $e;
        }
    }
}
