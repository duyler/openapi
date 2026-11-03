<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorWithContextDynamicScopeTest extends TestCase
{
    private ValidatorPool $pool;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Dynamic scope test', '1.0.0'),
            components: new Components(schemas: []),
        );
    }

    #[Test]
    public function validate_with_context_restores_scope_for_id_bearing_root(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(id: 'https://example.com/root', type: 'string');
        $context = $this->createContext();

        $validator->validateWithContext('value', $schema, $context);

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function validate_with_context_enters_root_frame_for_schema_without_id(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(type: 'string');
        $context = $this->createContext();

        $validator->validateWithContext('value', $schema, $context);

        $this->assertTrue($context->hasEmptyDynamicScope());
    }

    #[Test]
    public function validate_with_context_restores_scope_on_validation_failure(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(type: 'integer');
        $context = $this->createContext();

        try {
            $validator->validateWithContext('value', $schema, $context);
            self::fail('Expected ValidationException for non-integer data');
        } catch (ValidationException) {
        }

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function validate_with_context_keeps_scope_balanced_across_nested_properties(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(
            id: 'https://example.com/root',
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'nested' => new Schema(
                    type: 'object',
                    properties: [
                        'link' => new Schema(id: 'https://example.com/nested', type: 'string'),
                    ],
                ),
            ],
        );
        $context = $this->createContext();

        $validator->validateWithContext(['name' => 'value', 'nested' => ['link' => 'value']], $schema, $context);

        $this->assertTrue($context->hasEmptyDynamicScope());
    }

    #[Test]
    public function validate_with_context_keeps_scope_balanced_through_one_of_branches(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(
            oneOf: [
                new Schema(id: 'https://example.com/branch-object', type: 'object'),
                new Schema(type: 'string'),
            ],
        );
        $context = $this->createContext();

        $validator->validateWithContext('value', $schema, $context);

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function validate_with_context_ignoring_discriminator_restores_scope(): void
    {
        $validator = $this->createValidator();
        $schema = new Schema(id: 'https://example.com/root', type: 'string');
        $context = $this->createContext();

        $validator->validateWithContextIgnoringDiscriminator('value', $schema, $context);

        $this->assertTrue($context->hasEmptyDynamicScope());
    }

    private function createValidator(): SchemaValidatorWithContext
    {
        return new SchemaValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: new RefResolver(),
                statelessValidators: new StatelessValidatorRegistry($this->pool, BuiltinFormats::create()),
            ),
        );
    }

    private function createContext(): ValidationContext
    {
        return ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
    }
}
