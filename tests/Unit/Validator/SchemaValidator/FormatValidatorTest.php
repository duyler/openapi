<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function is_float;
use function is_int;
use function is_string;

#[CoversClass(FormatValidator::class)]
class FormatValidatorTest extends TestCase
{
    private const string SCHEMA_REF = '#/components/schemas/TestSchema';

    #[Test]
    #[DataProvider('provideValidFormatValues')]
    public function valid_format_value_passes_schema_validation(string $format, string $type, mixed $value): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec($type, $format))
            ->build();

        $succeeded = false;

        try {
            $validator->validateSchema($value, self::SCHEMA_REF);
            $succeeded = true;
        } catch (ValidationException|InvalidFormatException $e) {
            self::fail(sprintf('Expected %s format to pass, got: %s', $format, $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    #[DataProvider('provideInvalidFormatValues')]
    public function invalid_format_value_throws_invalid_format_exception(string $format, string $type, mixed $value): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec($type, $format))
            ->build();

        $caught = null;

        try {
            $validator->validateSchema($value, self::SCHEMA_REF);
            self::fail(sprintf('Expected InvalidFormatException for format %s with value %s', $format, $this->valueToString($value)));
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(InvalidFormatException::class, $caught);
        self::assertSame('format', $caught->keyword());
        self::assertSame($format, $caught->params()['format']);
        self::assertSame('/format', $caught->schemaPath());
    }

    #[Test]
    public function invalid_email_exception_contains_correct_keyword_and_params(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec('string', 'email'))
            ->build();

        $caught = null;

        try {
            $validator->validateSchema('not-an-email', self::SCHEMA_REF);
            self::fail('Expected InvalidFormatException for invalid email');
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(InvalidFormatException::class, $caught);
        self::assertSame('email', $caught->format);
        self::assertSame('email', $caught->params()['format']);
        self::assertSame('not-an-email', $caught->params()['value']);
        self::assertSame('format', $caught->getType());
        self::assertSame('', $caught->dataPath());
    }

    #[Test]
    public function float_format_rejects_integer_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec('number', 'float'))
            ->build();

        $caught = null;

        try {
            $validator->validateSchema(42, self::SCHEMA_REF);
            self::fail('Expected InvalidFormatException for integer value with float format');
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(InvalidFormatException::class, $caught);
        self::assertSame('float', $caught->params()['format']);
        self::assertSame(42, $caught->params()['value']);
    }

    #[Test]
    public function double_format_rejects_integer_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec('number', 'double'))
            ->build();

        $caught = null;

        try {
            $validator->validateSchema(7, self::SCHEMA_REF);
            self::fail('Expected InvalidFormatException for integer value with double format');
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(InvalidFormatException::class, $caught);
        self::assertSame('double', $caught->params()['format']);
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function provideValidFormatValues(): array
    {
        return [
            'email' => ['email', 'string', 'user@example.com'],
            'uri' => ['uri', 'string', 'https://example.com'],
            'uuid' => ['uuid', 'string', '550e8400-e29b-41d4-a716-446655440000'],
            'date-time' => ['date-time', 'string', '2024-01-15T10:30:00Z'],
            'date' => ['date', 'string', '2024-01-15'],
            'time' => ['time', 'string', '10:30:00Z'],
            'hostname' => ['hostname', 'string', 'example.com'],
            'ipv4' => ['ipv4', 'string', '192.168.1.1'],
            'ipv6' => ['ipv6', 'string', '2001:db8::1'],
            'byte' => ['byte', 'string', 'SGVsbG8gd29ybGQ='],
            'duration' => ['duration', 'string', 'P3Y6M4DT12H30M5S'],
            'json-pointer' => ['json-pointer', 'string', '/path/to/value'],
            'relative-json-pointer' => ['relative-json-pointer', 'string', '0'],
            'float' => ['float', 'number', 3.14],
            'double' => ['double', 'number', 3.14159265359],
        ];
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function provideInvalidFormatValues(): array
    {
        return [
            'email' => ['email', 'string', 'not-an-email'],
            'uri' => ['uri', 'string', 'not-a-uri'],
            'uuid' => ['uuid', 'string', 'not-a-uuid'],
            'date-time' => ['date-time', 'string', 'not-a-datetime'],
            'date' => ['date', 'string', 'not-a-date'],
            'time' => ['time', 'string', 'not-a-time'],
            'hostname' => ['hostname', 'string', '-invalid-host'],
            'ipv4' => ['ipv4', 'string', '999.999.999.999'],
            'ipv6' => ['ipv6', 'string', 'not-an-ipv6'],
            'byte' => ['byte', 'string', '!!!not-base64!!!'],
            'duration' => ['duration', 'string', 'not-a-duration'],
            'json-pointer' => ['json-pointer', 'string', 'invalid pointer'],
            'relative-json-pointer' => ['relative-json-pointer', 'string', 'not-a-pointer'],
            'float' => ['float', 'number', 42],
            'double' => ['double', 'number', 7],
        ];
    }

    private function buildSpec(string $type, string $format): string
    {
        return <<<YAML
openapi: 3.2.0
info:
  title: Format Validator Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    TestSchema:
      type: {$type}
      format: {$format}
YAML;
    }

    private function valueToString(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '<complex>';
    }
}
