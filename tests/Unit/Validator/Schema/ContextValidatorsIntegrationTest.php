<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\ItemsValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorRegistry;
use Duyler\OpenApi\Validator\Schema\PropertiesValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ContextValidatorsIntegrationTest extends TestCase
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
    public function items_validate_with_context_array_type_error(): void
    {
        $validator = new ItemsValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            nullableAsType: false,
        ));

        $caught = false;
        try {
            $validator->validateWithContext(['not_an_int'], $schema, $context);
        } catch (ValidationException $e) {
            $caught = true;
            self::assertNotEmpty($e->getErrors());
        }

        self::assertTrue($caught, 'Expected ValidationException for invalid items');
    }

    #[Test]
    public function properties_validate_with_context_type_error(): void
    {
        $validator = new PropertiesValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $schema = new Schema(
            type: 'object',
            properties: [
                'count' => new Schema(type: 'integer'),
            ],
        );

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $this->pool,
            errorFormatter: new SimpleFormatter(),
            nullableAsType: false,
        ));

        $caught = false;
        try {
            $validator->validateWithContext(['count' => 'not_an_int'], $schema, $context);
        } catch (ValidationException $e) {
            $caught = true;
            self::assertNotEmpty($e->getErrors());
        }

        self::assertTrue($caught, 'Expected ValidationException for invalid properties');
    }

    #[Test]
    public function custom_validator_under_builtin_items_keyword_runs_in_addition_to_context_dispatch(): void
    {
        $spy = new ItemsSpyKeywordValidator();
        $customKeywordValidators = new KeywordValidatorRegistry()->with('items', $spy);
        $statelessValidators = new StatelessValidatorRegistry(
            $this->pool,
            BuiltinFormats::create(),
            customKeywordValidators: $customKeywordValidators,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $this->pool,
            refResolver: $this->refResolver,
            statelessValidators: $statelessValidators,
            customKeywordValidators: $customKeywordValidators,
        );
        $validator = $dependencies->rootSchemaValidator($this->document, new ValidatorConfiguration());

        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $caught = false;
        try {
            $validator->validate(['not_an_int'], $schema);
        } catch (ValidationException $exception) {
            $caught = true;
            self::assertNotEmpty($exception->getErrors());
        }

        self::assertTrue($caught, 'Built-in items handling must not be replaced by a custom registration');
        self::assertSame([$schema], $spy->validatedSchemas);
    }
}

final class ItemsSpyKeywordValidator implements KeywordValidatorInterface
{
    /** @var list<Schema> */
    public array $validatedSchemas = [];

    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return null !== $schema->items;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void
    {
        $this->validatedSchemas[] = $schema;
    }
}
