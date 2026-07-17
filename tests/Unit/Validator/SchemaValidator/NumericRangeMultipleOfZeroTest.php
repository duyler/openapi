<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidMultipleOfSchemaException;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumericRangeValidator::class)]
#[CoversClass(InvalidMultipleOfSchemaException::class)]
class NumericRangeMultipleOfZeroTest extends TestCase
{
    private NumericRangeValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new NumericRangeValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function multiple_of_zero_throws_schema_exception(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $this->expectException(InvalidMultipleOfSchemaException::class);

        $this->validator->validate(7, $schema);
    }

    #[Test]
    public function multiple_of_zero_message_indicates_schema_error(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $caught = null;

        try {
            $this->validator->validate(7, $schema);
        } catch (InvalidMultipleOfSchemaException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('positive', $caught->getMessage());
        self::assertStringContainsString('schema', strtolower($caught->getMessage()));
    }

    #[Test]
    public function non_zero_multiple_of_still_throws_value_level_error(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 2.0);

        $this->expectException(MultipleOfKeywordError::class);

        $this->validator->validate(7, $schema);
    }
}
