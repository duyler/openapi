<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DurationValidator::class)]
final class DurationValidatorTest extends TestCase
{
    private DurationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DurationValidator();
    }

    public static function validDurationValuesProvider(): array
    {
        return [
            'one year' => ['P1Y'],
            'years and months' => ['P2Y6M'],
            'one day' => ['P1D'],
            'day and hours' => ['P1DT12H'],
            'minutes only' => ['PT30M'],
            'hours and minutes' => ['PT1H30M'],
            'full time' => ['PT1H30M15S'],
            'full duration' => ['P1Y2M3DT4H5M6S'],
            'seconds only' => ['PT45S'],
            'large values' => ['P100Y'],
            'months only' => ['P6M'],
            'hours only' => ['PT24H'],
        ];
    }

    #[DataProvider('validDurationValuesProvider')]
    #[Test]
    public function valid_duration_values_pass(string $value): void
    {
        $this->validator->validate($value);

        $this->assertTrue(true);
    }

    public static function invalidDurationValuesProvider(): array
    {
        return [
            'missing P prefix' => ['not-a-duration'],
            'empty P' => ['P'],
            'empty PT' => ['PT'],
            'number without designator' => ['P1'],
            'T with missing time value' => ['P1YT0H0M0'],
            'lowercase p' => ['p1Y'],
            'spaces' => ['P 1Y'],
            'negative value' => ['-P1Y'],
            'decimal value' => ['P1.5Y'],
            'random string' => ['xyz'],
        ];
    }

    #[DataProvider('invalidDurationValuesProvider')]
    #[Test]
    public function invalid_duration_values_throw_exception(string $value): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');

        $this->validator->validate(123);
    }

    #[Test]
    public function throw_error_for_null(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate(null);
    }

    #[Test]
    public function exception_contains_format_name(): void
    {
        try {
            $this->validator->validate('invalid');
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('duration', $exception->format);
        }
    }

    #[Test]
    public function exception_contains_invalid_value(): void
    {
        $invalidValue = 'invalid-duration';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame($invalidValue, $exception->value);
        }
    }
}
