<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
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

final class OpenApiValidatorBuilderWithKeywordValidatorTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    EvenNumber:
      type: integer
      x-even: true
YAML;

    private const string EVEN_SCHEMA_REF = '#/components/schemas/EvenNumber';

    #[Test]
    public function registered_keyword_validator_rejects_odd_instance(): void
    {
        $validator = $this->buildValidator(withKeywordValidator: true);

        try {
            $validator->validateSchema(3, self::EVEN_SCHEMA_REF);
            self::fail(sprintf('Expected %s for odd instance', ValidationException::class));
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            self::assertCount(1, $errors);
            self::assertSame('x-even', $errors[0]->keyword());
            self::assertSame('/', $errors[0]->dataPath());
        }
    }

    #[Test]
    public function registered_keyword_validator_accepts_even_instance(): void
    {
        $validator = $this->buildValidator(withKeywordValidator: true);

        $validator->validateSchema(2, self::EVEN_SCHEMA_REF);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unregistered_keyword_stays_annotation_and_does_not_affect_outcome(): void
    {
        $validator = $this->buildValidator(withKeywordValidator: false);

        $validator->validateSchema(3, self::EVEN_SCHEMA_REF);

        $stack = $this->extractValidationStack($validator);
        $schema = $validator->getDocument()->components?->schemas['EvenNumber'] ?? null;

        self::assertInstanceOf(Schema::class, $schema);

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $validator->getPool(),
            errorFormatter: $stack->errorFormatter,
        ));

        $stack->schemaValidatorWithContext->validateWithContext(3, $schema, $context);

        $annotations = $context->annotations();

        self::assertArrayHasKey('x-even', $annotations);
        self::assertSame([['path' => '/', 'value' => true]], $annotations['x-even']);
    }

    #[Test]
    public function re_registering_same_keyword_replaces_validator_without_duplicate_dispatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withKeywordValidator('x-even', new EvenNumberKeywordValidator())
            ->withKeywordValidator('x-even', new EvenNumberKeywordValidator())
            ->build();

        try {
            $validator->validateSchema(3, self::EVEN_SCHEMA_REF);
            self::fail(sprintf('Expected %s for odd instance', ValidationException::class));
        } catch (ValidationException $exception) {
            self::assertCount(1, $exception->getErrors());
        }
    }

    private function buildValidator(bool $withKeywordValidator): OpenApiValidator
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC);

        if ($withKeywordValidator) {
            $builder = $builder->withKeywordValidator('x-even', new EvenNumberKeywordValidator());
        }

        return $builder->build();
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

final readonly class EvenNumberKeywordValidator implements KeywordValidatorInterface
{
    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return null !== ($schema->extras['x-even'] ?? null);
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void
    {
        if (false === is_int($data) || 0 !== $data % 2) {
            throw new OddNumberError($data, $context->path());
        }
    }
}

final class OddNumberError extends AbstractValidationError
{
    public function __construct(mixed $data, string $dataPath)
    {
        parent::__construct(
            message: sprintf('Value "%s" is not an even number at %s', $data, $dataPath),
            keyword: 'x-even',
            dataPath: $dataPath,
            schemaPath: '/x-even',
            params: ['actual' => $data],
            suggestion: 'Use an even number',
        );
    }
}
