<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Registry;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Registry\DefaultValidatorRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\ValidatorPool;

final class SchemaValidatorWithRegistryTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function validate_with_registry_throws_type_mismatch_error(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $schemaValidator->validate(123, $schema);
    }

    #[Test]
    public function validate_with_registry_passes_for_valid_data(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(type: 'string');

        $schemaValidator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_registry_passes_for_valid_data(): void
    {
        $schemaValidator = new SchemaValidator($this->pool);
        $schema = new Schema(type: 'string');

        $schemaValidator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_custom_registry(): void
    {
        $customRegistry = $this->createCustomRegistry();
        $schemaValidator = new SchemaValidator($this->pool, registry: $customRegistry);
        $schema = new Schema(type: 'string');

        $schemaValidator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_custom_registry_throws_error(): void
    {
        $customRegistry = $this->createCustomRegistry();
        $schemaValidator = new SchemaValidator($this->pool, registry: $customRegistry);
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $schemaValidator->validate(123, $schema);
    }

    #[Test]
    public function validate_with_registry_and_format_registry(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(type: 'string', format: 'email');

        $schemaValidator->validate('test@example.com', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_registry_validates_multiple_keywords(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(
            type: 'string',
            minLength: 3,
            maxLength: 10,
        );

        $schemaValidator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_registry_validates_minLength(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(type: 'string', minLength: 5);

        $this->expectExceptionMessage('less than minimum');

        $schemaValidator->validate('abc', $schema);
    }

    #[Test]
    public function validate_with_registry_validates_maxLength(): void
    {
        $registry = new DefaultValidatorRegistry($this->pool);
        $schemaValidator = new SchemaValidator($this->pool, registry: $registry);
        $schema = new Schema(type: 'string', maxLength: 5);

        $this->expectExceptionMessage('exceeds maximum');

        $schemaValidator->validate('hello world', $schema);
    }

    private function createCustomRegistry(): ValidatorRegistryInterface
    {
        return new readonly class ($this->pool) implements ValidatorRegistryInterface {
            public function __construct(private ValidatorPool $pool) {}

            #[Override]
            public function getValidator(string $type): SchemaValidatorInterface
            {
                return new TypeValidator($this->pool);
            }

            #[Override]
            public function getAllValidators(): iterable
            {
                return [
                    TypeValidator::class => new TypeValidator($this->pool),
                ];
            }
        };
    }
}
