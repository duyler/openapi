<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;

use function spl_object_id;

/**
 * @internal
 */
final class SchemaValidatorWithContextHotPathTest extends TestCase
{
    #[Test]
    public function root_schema_validator_returns_shared_instance_for_same_document(): void
    {
        $document = $this->buildDocument();
        $dependencies = $this->buildDependencies();

        $first = $dependencies->rootSchemaValidator($document, new ValidatorConfiguration());
        $second = $dependencies->rootSchemaValidator($document, new ValidatorConfiguration());

        self::assertSame(
            spl_object_id($first),
            spl_object_id($second),
            'rootSchemaValidator must return the same instance for the same document',
        );
    }

    #[Test]
    public function root_schema_validator_distinct_per_document(): void
    {
        $dependencies = $this->buildDependencies();
        $documentA = $this->buildDocument();
        $documentB = $this->buildDocument();

        $first = $dependencies->rootSchemaValidator($documentA, new ValidatorConfiguration());
        $second = $dependencies->rootSchemaValidator($documentB, new ValidatorConfiguration());

        self::assertNotSame(
            spl_object_id($first),
            spl_object_id($second),
            'rootSchemaValidator must isolate distinct documents',
        );
    }

    #[Test]
    public function nested_property_validation_does_not_allocate_new_validator(): void
    {
        $document = $this->buildDocument();
        $dependencies = $this->buildDependencies();

        $rootValidator = $dependencies->rootSchemaValidator($document, new ValidatorConfiguration());

        $nestedSchema = new Schema(
            type: 'object',
            properties: [
                'inner' => new Schema(
                    type: 'object',
                    properties: [
                        'leaf' => new Schema(type: 'string'),
                    ],
                ),
                'sibling' => new Schema(type: 'integer'),
            ],
        );

        $rootValidator->validate(['inner' => ['leaf' => 'x'], 'sibling' => 1], $nestedSchema);

        $reused = $dependencies->rootSchemaValidator($document, new ValidatorConfiguration());

        self::assertSame(
            spl_object_id($rootValidator),
            spl_object_id($reused),
            'Nested validation must reuse the shared root validator',
        );
    }

    #[Test]
    public function applicable_stateless_validators_filtered_by_keyword_for_simple_string(): void
    {
        $rootValidator = new SchemaValidatorWithContext(
            $this->buildDocument(),
            $this->buildDependencies(),
        );

        $reflection = new ReflectionMethod($rootValidator, 'computeApplicableStatelessValidators');
        $validators = $reflection->invoke($rootValidator, new Schema(type: 'string'));

        $keywords = [];
        foreach ($validators as $validator) {
            $keywords[] = $validator::class;
        }

        self::assertNotContains(
            PropertiesValidator::class,
            $keywords,
        );
        self::assertNotContains(
            ItemsValidator::class,
            $keywords,
        );
        self::assertNotContains(
            NumericRangeValidator::class,
            $keywords,
        );
        self::assertContains(
            TypeValidator::class,
            $keywords,
        );
    }

    #[Test]
    public function applicable_stateless_validators_for_minLength_schema(): void
    {
        $rootValidator = new SchemaValidatorWithContext(
            $this->buildDocument(),
            $this->buildDependencies(),
        );

        $reflection = new ReflectionMethod($rootValidator, 'computeApplicableStatelessValidators');
        $validators = $reflection->invoke(
            $rootValidator,
            new Schema(type: 'string', minLength: 3),
        );

        $keywords = [];
        foreach ($validators as $validator) {
            $keywords[] = $validator::class;
        }

        self::assertContains(
            StringLengthValidator::class,
            $keywords,
            'StringLengthValidator must be applicable when minLength is set',
        );
        self::assertContains(
            TypeValidator::class,
            $keywords,
        );
        self::assertNotContains(
            NumericRangeValidator::class,
            $keywords,
        );
    }

    private function buildDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );
    }

    private function buildDependencies(): SchemaValidatorDependencies
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();

        return new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
        );
    }
}
