<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * @internal
 */
final class TimeValidatorOffsetRequiredRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths: {}
components:
  schemas:
    Event:
      type: object
      required: [at]
      properties:
        at: { type: string, format: time }
      additionalProperties: false
YAML;

    #[Test]
    #[DataProvider('acceptedTimeValues')]
    public function accepted_time_values_pass_validation(string $time): void
    {
        $validator = $this->buildValidator();

        $validator->validateSchema(['at' => $time], '#/components/schemas/Event');

        self::expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('rejectedTimeValues')]
    public function rejected_time_values_fail_validation(string $time): void
    {
        $validator = $this->buildValidator();

        $caught = $this->captureInvalidFormat(fn(): mixed => $validator->validateSchema(
            ['at' => $time],
            '#/components/schemas/Event',
        ));

        self::assertNotNull($caught, sprintf('Time value "%s" must be rejected.', $time));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function acceptedTimeValues(): iterable
    {
        yield 'utc upper Z' => ['10:30:00Z'];
        yield 'utc lower z' => ['10:30:00z'];
        yield 'positive numeric offset' => ['10:30:00+03:00'];
        yield 'negative numeric offset' => ['10:30:00-05:00'];
        yield 'negative zero offset' => ['10:30:00-00:00'];
        yield 'fractional seconds with offset' => ['10:30:00.5Z'];
        yield 'midnight with offset' => ['00:00:00+00:00'];
        yield 'max positive offset' => ['10:30:00+14:00'];
        yield 'max negative offset' => ['10:30:00-14:00'];
        yield 'leap second UTC end of day' => ['23:59:60Z'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rejectedTimeValues(): iterable
    {
        yield 'basic time without offset' => ['10:30:00'];
        yield 'midnight without offset' => ['00:00:00'];
        yield 'fractional seconds without offset' => ['10:30:00.5'];
        yield 'leap second without offset' => ['23:59:60'];
        yield 'leap second with non-UTC offset' => ['23:59:60+05:00'];
        yield 'offset exceeds plus fourteen hours' => ['10:30:00+15:00'];
        yield 'offset exceeds minus fourteen hours' => ['10:30:00-15:00'];
        yield 'offset minute boundary violation' => ['10:30:00+03:60'];
        yield 'non-numeric offset suffix' => ['10:30:00XYZ'];
        yield 'letters in time' => ['aa:bb:cc'];
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    /**
     * @param callable(): mixed $callback
     */
    private function captureInvalidFormat(callable $callback): ?InvalidFormatException
    {
        try {
            $callback();
        } catch (InvalidFormatException $e) {
            return $e;
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                if ($error instanceof InvalidFormatException) {
                    return $error;
                }
            }
        }

        return null;
    }
}
