<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

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
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function accepts_one_week_duration(): void
    {
        try {
            $this->validator->validate('P1W');
        } catch (InvalidFormatException $exception) {
            $this->fail(sprintf(
                'P1W should be accepted as valid ISO 8601 duration, got: %s',
                $exception->getMessage(),
            ));
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_weeks_combined_with_days(): void
    {
        $invalidValue = 'P1W1D';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('P1W1D should be rejected: weeks are exclusive and cannot combine with days');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('duration', $exception->format);
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function accepts_two_weeks_duration(): void
    {
        try {
            $this->validator->validate('P2W');
        } catch (InvalidFormatException $exception) {
            $this->fail(sprintf(
                'P2W should be accepted as valid ISO 8601 duration, got: %s',
                $exception->getMessage(),
            ));
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_weeks_combined_with_hours(): void
    {
        $invalidValue = 'P1W1H';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('P1W1H should be rejected: weeks are exclusive and cannot combine with hours');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('duration', $exception->format);
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function accepts_zero_weeks_duration(): void
    {
        try {
            $this->validator->validate('P0W');
        } catch (InvalidFormatException $exception) {
            $this->fail(sprintf(
                'P0W should be accepted: ISO 8601 allows zero as valid number of weeks, got: %s',
                $exception->getMessage(),
            ));
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_weeks_without_number(): void
    {
        $invalidValue = 'PW';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('PW should be rejected: number of weeks is required before W designator');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('duration', $exception->format);
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    /**
     * Weeks (W designator) edge cases per ISO 8601: the W designator is an
     * alternative to Y/M/D + H/M/S. Weeks are exclusive — they cannot be
     * combined with any other component.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function weeksEdgeCasesProvider(): array
    {
        return [
            'P1W — one week is valid' => ['P1W', true],
            'P2W — two weeks is valid' => ['P2W', true],
            'P0W — zero weeks is valid' => ['P0W', true],
            'P10W — large number of weeks is valid' => ['P10W', true],
            'P1W1D — weeks with days is invalid (weeks are exclusive)' => ['P1W1D', false],
            'P1W1H — weeks with hours is invalid (weeks are exclusive)' => ['P1W1H', false],
            'P1W1Y — weeks with years is invalid' => ['P1W1Y', false],
            'P1W1M — weeks with months is invalid' => ['P1W1M', false],
            'PW — weeks without number is invalid' => ['PW', false],
        ];
    }

    #[DataProvider('weeksEdgeCasesProvider')]
    #[Test]
    public function weeks_edge_cases_match_expected_validation_result(string $duration, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->validator->validate($duration);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'Duration "%s" was expected to be %s but is %s',
                $duration,
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }
}
