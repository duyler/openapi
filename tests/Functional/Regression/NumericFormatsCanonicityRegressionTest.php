<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class NumericFormatsCanonicityRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Numeric Formats Canonicity
  version: '1'
paths: {}
components:
  schemas:
    Int32Value:
      type: integer
      format: int32
    Int64Value:
      type: integer
      format: int64
    FloatValue:
      type: number
      format: float
    DoubleValue:
      type: number
      format: double
YAML;

    #[Test]
    public function integer_float_out_of_int32_range_is_invalid(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(3.0e9, '#/components/schemas/Int32Value');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Audit probe: integer float 3e9 exceeds int32 range — must be a range error, not a silent pass.',
        );
        self::assertStringContainsString('Integer out of int32 range', null === $caught ? '' : $caught->getMessage());
    }

    #[Test]
    public function integer_float_within_int32_range_is_valid(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(2.0e0, '#/components/schemas/Int32Value');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull($caught, 'Integer float 2.0 within int32 range must be VALID.');
    }

    #[Test]
    public function int32_boundary_2147483647_is_valid(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(2_147_483_647, '#/components/schemas/Int32Value');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull($caught, 'int32 upper boundary 2^31-1 must be VALID.');
    }

    #[Test]
    public function non_integer_float_is_invalid_for_int32(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(1.5, '#/components/schemas/Int32Value');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Non-integer float 1.5 must be rejected as "requires an integer value".');
    }

    #[Test]
    public function integer_float_beyond_php_int_is_invalid_for_int64(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(1.0e19, '#/components/schemas/Int64Value');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            '1e19 exceeds PHP_INT_MAX — int64 range check must compare as float and reject.',
        );
    }

    #[Test]
    public function integer_value_is_valid_for_float_format(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(5, '#/components/schemas/FloatValue');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            'Audit probe: integer 5 is mathematically a number — type:number + format:float must accept it.',
        );
    }

    #[Test]
    public function integer_value_is_valid_for_double_format(): void
    {
        $validator = $this->build();

        $caught = null;

        try {
            $validator->validateSchema(5, '#/components/schemas/DoubleValue');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull($caught, 'integer 5 must be accepted by format:double under type:number.');
    }

    private function build(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }
}
