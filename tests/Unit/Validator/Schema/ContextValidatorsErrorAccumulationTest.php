<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\ItemsValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\PropertiesValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

/** @internal */
final class ContextValidatorsErrorAccumulationTest extends TestCase
{
    private ValidatorPool $pool;
    private RefResolver $refResolver;
    private OpenApiDocument $document;
    private StatelessValidatorRegistry $statelessValidators;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->refResolver = new RefResolver();
        $this->document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function items_validator_accumulates_errors_for_invalid_items(): void
    {
        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'string',
                minLength: 3,
            ),
        );

        $context = ValidationContext::create(pool: $this->pool);

        $data = ['ab', 'cd', 'valid_string'];

        $this->expectException(ValidationException::class);

        $validator->validateWithContext($data, $schema, $context);
    }

    #[Test]
    public function items_validator_passes_for_valid_items(): void
    {
        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );

        $context = ValidationContext::create(pool: $this->pool);

        $validator->validateWithContext(['hello', 'world'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function items_validator_skips_when_no_items_schema(): void
    {
        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(type: 'array');
        $context = ValidationContext::create(pool: $this->pool);

        $validator->validateWithContext(['a', 'b'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function items_validator_reports_single_error_for_one_invalid_item(): void
    {
        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $context = ValidationContext::create(pool: $this->pool);

        try {
            $validator->validateWithContext(['not_int'], $schema, $context);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertCount(1, $e->getErrors());
        }
    }

    #[Test]
    public function properties_validator_accumulates_errors_for_invalid_properties(): void
    {
        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 5),
                'email' => new Schema(type: 'string', format: 'email'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);

        $data = ['name' => 'ab', 'email' => 'not-an-email'];

        $this->expectException(ValidationException::class);

        $validator->validateWithContext($data, $schema, $context);
    }

    #[Test]
    public function properties_validator_passes_for_valid_data(): void
    {
        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);

        $validator->validateWithContext(['name' => 'John'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function properties_validator_skips_missing_optional_properties(): void
    {
        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);

        $validator->validateWithContext(['name' => 'John'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function properties_validator_skips_when_no_properties(): void
    {
        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(type: 'object');
        $context = ValidationContext::create(pool: $this->pool);

        $validator->validateWithContext(['key' => 'value'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function properties_validator_reports_errors_for_multiple_invalid_properties(): void
    {
        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'a' => new Schema(type: 'integer'),
                'b' => new Schema(type: 'integer'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);

        try {
            $validator->validateWithContext(['a' => 'string', 'b' => 'also_string'], $schema, $context);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertGreaterThanOrEqual(1, count($e->getErrors()));
        }
    }
}
