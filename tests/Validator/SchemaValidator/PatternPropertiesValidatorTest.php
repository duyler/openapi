<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PatternPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PatternPropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PatternPropertiesValidator($this->pool);
    }

    #[Test]
    public function validate_pattern_properties(): void
    {
        $patternSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $this->validator->validate(['meta_info' => 'data'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_multiple_patterns(): void
    {
        $patternSchema1 = new Schema(type: 'string', minLength: 3);
        $patternSchema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^str_/' => $patternSchema1,
                '/^num_/' => $patternSchema2,
            ],
        );

        $this->validator->validate(['str_val' => 'hello', 'num_val' => 42], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_pattern_property(): void
    {
        $patternSchema = new Schema(type: 'string', minLength: 10);
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['meta_info' => 'short'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^key_/' => $patternSchema,
            ],
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_pattern_properties_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_pattern_properties_is_empty(): void
    {
        $schema = new Schema(type: 'object', patternProperties: []);

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_non_matching_properties(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $this->validator->validate(['other_key' => 'value', 'meta_info' => 'data'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_empty_pattern(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '' => $patternSchema,
            ],
        );

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_numeric_keys(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^\d+$/' => $patternSchema,
            ],
        );

        $this->validator->validate([0 => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
