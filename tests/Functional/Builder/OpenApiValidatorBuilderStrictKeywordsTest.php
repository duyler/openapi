<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\Validation\ValidationStack;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

use function is_int;
use function sprintf;

final class OpenApiValidatorBuilderStrictKeywordsTest extends TestCase
{
    private const string TYPO_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Typo
  version: 1.0.0
components:
  schemas:
    Name:
      type: object
      properties:
        name:
          type: string
      requird:
        - name
YAML;

    private const string VENDOR_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Vendor extension
  version: 1.0.0
components:
  schemas:
    Tagged:
      type: object
      properties:
        name:
          type: string
      x-anything:
        nested:
          - deep
YAML;

    private const string EVEN_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Custom keyword
  version: 1.0.0
components:
  schemas:
    EvenNumber:
      type: integer
      even: true
YAML;

    private const string EVEN_SCHEMA_REF = '#/components/schemas/EvenNumber';

    #[Test]
    public function typo_keyword_is_rejected_on_build_by_default(): void
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString(self::TYPO_SPEC);

        try {
            $builder->build();

            self::fail(sprintf('Expected %s for the typo keyword', InvalidSchemaException::class));
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString(
                "Unknown schema keyword 'requird' at #/components/schemas/Name",
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function vendor_extension_keyword_parses_and_validates(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::VENDOR_SPEC)
            ->build();

        $validator->validateSchema(['name' => 'duyler'], '#/components/schemas/Tagged');

        $schema = $validator->getDocument()->components?->schemas['Tagged'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey('x-anything', $schema->extras ?? []);
    }

    #[Test]
    public function registered_custom_keyword_builds_and_validates_without_allow_list(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EVEN_SPEC)
            ->withKeywordValidator('even', new StrictModeEvenKeywordValidator())
            ->build();

        $validator->validateSchema(2, self::EVEN_SCHEMA_REF);

        try {
            $validator->validateSchema(3, self::EVEN_SCHEMA_REF);

            self::fail(sprintf('Expected %s for the odd instance', ValidationException::class));
        } catch (ValidationException $exception) {
            self::assertSame('even', $exception->getErrors()[0]->keyword());
        }
    }

    #[Test]
    public function disabled_strict_keywords_keep_annotation_mode(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TYPO_SPEC)
            ->disableStrictKeywords()
            ->build();

        $validator->validateSchema(['name' => 'duyler'], '#/components/schemas/Name');

        $schema = $validator->getDocument()->components?->schemas['Name'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey('requird', $schema->extras ?? []);

        $annotations = $this->collectAnnotations($validator, $schema);

        self::assertArrayHasKey('requird', $annotations);
    }

    #[Test]
    public function allow_listed_keyword_is_legal(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TYPO_SPEC)
            ->withAllowedUnknownKeywords('requird')
            ->build();

        $validator->validateSchema(['name' => 'duyler'], '#/components/schemas/Name');

        $schema = $validator->getDocument()->components?->schemas['Name'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey('requird', $schema->extras ?? []);
    }

    #[Test]
    public function allow_list_accumulates_across_calls(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TYPO_SPEC)
            ->withAllowedUnknownKeywords('other-thing')
            ->withAllowedUnknownKeywords('requird')
            ->build();

        $schema = $validator->getDocument()->components?->schemas['Name'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey('requird', $schema->extras ?? []);
    }

    #[Test]
    public function empty_allow_list_call_keeps_accumulated_keywords(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TYPO_SPEC)
            ->withAllowedUnknownKeywords('requird')
            ->withAllowedUnknownKeywords()
            ->build();

        $schema = $validator->getDocument()->components?->schemas['Name'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);
        self::assertArrayHasKey('requird', $schema->extras ?? []);
    }

    /**
     * @return array<string, list<array{path: string, value: mixed}>>
     */
    private function collectAnnotations(OpenApiValidatorInterface $validator, Schema $schema): array
    {
        $stack = $this->extractValidationStack($validator);

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $validator->getPool(),
            errorFormatter: $stack->errorFormatter,
        ));

        $stack->schemaValidatorWithContext->validateWithContext(['name' => 'duyler'], $schema, $context);

        return $context->annotations();
    }

    private function extractValidationStack(OpenApiValidator $validator): ValidationStack
    {
        /** @var ValidatorDependencies $dependencies */
        $dependencies = $this->readPrivateProperty($validator, 'dependencies');

        /** @var ValidationStack $stack */
        $stack = $this->readPrivateProperty($dependencies->schemaValidation, 'context');

        return $stack;
    }

    private function readPrivateProperty(object $object, string $property): mixed
    {
        return new ReflectionObject($object)->getProperty($property)->getValue($object);
    }
}

final readonly class StrictModeEvenKeywordValidator implements KeywordValidatorInterface
{
    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return null !== ($schema->extras['even'] ?? null);
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void
    {
        if (false === is_int($data) || 0 !== $data % 2) {
            throw new OddInstanceError($data, $context->path());
        }
    }
}

final class OddInstanceError extends AbstractValidationError
{
    public function __construct(mixed $data, string $dataPath)
    {
        parent::__construct(
            message: sprintf('Value "%s" is not an even number at %s', $data, $dataPath),
            keyword: 'even',
            dataPath: $dataPath,
            schemaPath: '/even',
            params: ['actual' => $data],
            suggestion: 'Use an even number',
        );
    }
}
