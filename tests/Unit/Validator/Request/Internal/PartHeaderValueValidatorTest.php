<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\Internal\PartHeaderValueValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartHeaderValueValidator::class)]
final class PartHeaderValueValidatorTest extends TestCase
{
    #[Test]
    public function header_without_schema_passes_without_validation(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Trace-Id', 'abc', new Header());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_schema_valid_value_passes(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Trace-Id', 'abc', new Header(schema: new Schema(type: 'string')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function string_schema_invalid_value_fails_with_type_mismatch(): void
    {
        $validator = $this->createValidator();

        try {
            $validator->validate('X-Trace-Id', '42', new Header(schema: new Schema(type: 'object')));
            $this->fail('Expected a validation failure for the non-object header value');
        } catch (TypeMismatchError $exception) {
            self::assertSame('X-Trace-Id', $exception->dataPath());

            return;
        }
    }

    #[Test]
    public function integer_schema_coerces_numeric_strings(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Rate-Limit', '42', new Header(schema: new Schema(type: 'integer')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function integer_schema_rejects_non_numeric_strings(): void
    {
        $validator = $this->createValidator();

        $this->expectException(TypeMismatchError::class);

        $validator->validate('X-Rate-Limit', 'abc', new Header(schema: new Schema(type: 'integer')));
    }

    #[Test]
    public function number_schema_coerces_numeric_strings(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Ratio', '1.5', new Header(schema: new Schema(type: 'number')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function number_schema_rejects_non_numeric_strings(): void
    {
        $validator = $this->createValidator();

        $this->expectException(TypeMismatchError::class);

        $validator->validate('X-Ratio', 'abc', new Header(schema: new Schema(type: 'number')));
    }

    #[Test]
    public function boolean_schema_coerces_boolean_strings(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Flag', 'true', new Header(schema: new Schema(type: 'boolean')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boolean_schema_rejects_non_boolean_strings(): void
    {
        $validator = $this->createValidator();

        $this->expectException(TypeMismatchError::class);

        $validator->validate('X-Flag', 'maybe', new Header(schema: new Schema(type: 'boolean')));
    }

    #[Test]
    public function array_schema_splits_comma_separated_values(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Tags', 'a, b, c', new Header(schema: new Schema(type: 'array')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function array_schema_rejects_value_beyond_the_item_limit(): void
    {
        $validator = $this->createValidator();

        $this->expectException(InvalidParameterException::class);

        $validator->validate(
            'X-Tags',
            str_repeat('a,', 1001) . 'a',
            new Header(schema: new Schema(type: 'array')),
        );
    }

    #[Test]
    public function object_schema_coerces_json_objects(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Meta', '{"a":1}', new Header(schema: new Schema(type: 'object')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function object_schema_rejects_malformed_json(): void
    {
        $validator = $this->createValidator();

        $this->expectException(TypeMismatchError::class);

        $validator->validate('X-Meta', '{malformed', new Header(schema: new Schema(type: 'object')));
    }

    #[Test]
    public function object_schema_treats_empty_value_as_an_empty_object(): void
    {
        $validator = $this->createValidator();

        $validator->validate('X-Meta', '', new Header(schema: new Schema(type: 'object')));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function boolean_false_schema_rejects_any_present_value(): void
    {
        $validator = $this->createValidator();

        $this->expectException(ValidationException::class);

        $validator->validate('Content-ID', '<cid:1>', new Header(schema: new Schema(not: new Schema())));
    }

    #[Test]
    public function format_schema_rejects_malformed_values(): void
    {
        $validator = $this->createValidator();

        $this->expectException(InvalidFormatException::class);

        $validator->validate(
            'Content-Location',
            'not a uri',
            new Header(schema: new Schema(type: 'string', format: 'uri')),
        );
    }

    private function createValidator(): PartHeaderValueValidator
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        )->rootStatelessValidator();

        return new PartHeaderValueValidator(
            schemaValidator: $schemaValidator,
            pool: $pool,
            config: new ParameterValidationConfig(),
        );
    }
}
