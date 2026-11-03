<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sprintf;

use const PHP_INT_MAX;

final class NumericFormatsUnderNumberDomainRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Numeric Formats Under Number Domain
  version: '1'
paths: {}
components:
  schemas:
    NumberInt32:
      type: number
      format: int32
    NumberInt64:
      type: number
      format: int64
YAML;

    #[Test]
    public function number_int32_rejects_three_e_nine(): void
    {
        $this->assertInvalid(3.0e9, '#/components/schemas/NumberInt32', 'Audit probe under type:number: 3e9 must hit the int32 range check.');
    }

    #[Test]
    public function number_int32_accepts_two_billion(): void
    {
        $this->assertValid(2.0e9, '#/components/schemas/NumberInt32');
    }

    #[Test]
    public function number_int64_accepts_php_int_max(): void
    {
        $this->assertValid(PHP_INT_MAX, '#/components/schemas/NumberInt64');
    }

    #[Test]
    public function number_int64_rejects_one_e_nineteen(): void
    {
        $this->assertInvalid(1.0e19, '#/components/schemas/NumberInt64', '1e19 exceeds the 64-bit range — the number-domain int64 format must reject it.');
    }

    #[Test]
    public function number_int32_rejects_non_integer_float(): void
    {
        $this->assertInvalid(1.5, '#/components/schemas/NumberInt32', 'A fractional float must be rejected by the int32 format under type:number.');
    }

    private function assertValid(mixed $data, string $schemaRef): void
    {
        $caught = null;

        try {
            $this->build()->validateSchema($data, $schemaRef);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            sprintf('Instance must be VALID against %s, got: %s', $schemaRef, null === $caught ? '' : $caught->getMessage()),
        );
    }

    private function assertInvalid(mixed $data, string $schemaRef, string $message): void
    {
        $caught = null;

        try {
            $this->build()->validateSchema($data, $schemaRef);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, $message);
    }

    private function build(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }
}
