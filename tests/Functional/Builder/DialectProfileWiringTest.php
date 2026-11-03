<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Validation\ValidationStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function sprintf;

final class DialectProfileWiringTest extends TestCase
{
    #[Test]
    public function build_with_30_document_carries_30_profile_in_ref_resolver(): void
    {
        $validator = $this->buildValidator('3.0.3');

        $refResolver = $this->readProperty($validator, 'dependencies')->refResolver;

        self::assertFalse($refResolver->dialectProfile()->allowTypeArray);
        self::assertFalse($refResolver->dialectProfile()->refSiblingsApply);
        self::assertTrue($refResolver->dialectProfile()->nullableRequiresType);
    }

    #[Test]
    public function build_with_31_document_carries_31_profile_in_ref_resolver(): void
    {
        $validator = $this->buildValidator('3.1.0');

        $refResolver = $this->readProperty($validator, 'dependencies')->refResolver;

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $refResolver->dialectProfile(),
        );
    }

    #[Test]
    public function build_with_30_document_derives_validation_context_with_30_profile(): void
    {
        $validator = $this->buildValidator('3.0.3');

        $context = $this->createRootContext($validator);

        self::assertEquals(
            DialectProfile::forOpenApi30(),
            $context->dialectProfile,
        );
    }

    #[Test]
    public function fork_of_context_from_30_document_keeps_30_profile(): void
    {
        $validator = $this->buildValidator('3.0.3');

        $child = $this->createRootContext($validator)->forkForBranch();

        self::assertEquals(
            DialectProfile::forOpenApi30(),
            $child->dialectProfile,
        );
    }

    #[Test]
    public function build_with_31_document_derives_validation_context_with_31_profile(): void
    {
        $validator = $this->buildValidator('3.1.0');

        $context = $this->createRootContext($validator);

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $context->dialectProfile,
        );
    }

    private function buildValidator(string $openapiVersion): OpenApiValidatorInterface
    {
        $yaml = sprintf(
            <<<'YAML'
openapi: %s
info:
  title: Dialect Wiring API
  version: 1.0.0
components:
  schemas:
    Ping:
      type: object
      properties:
        message:
          type: string
YAML,
            $openapiVersion,
        );

        return OpenApiValidatorBuilder::create()->fromYamlString($yaml)->build();
    }

    private function createRootContext(OpenApiValidatorInterface $validator): ValidationContext
    {
        $dependencies = $this->readProperty($validator, 'dependencies');
        $stack = $this->readProperty($dependencies->schemaValidation, 'context');
        self::assertInstanceOf(ValidationStack::class, $stack);
        $configuration = $this->readProperty($stack, 'validatorConfiguration');
        self::assertInstanceOf(ValidatorConfiguration::class, $configuration);

        return ValidationContext::fromInit(new ValidationContextInit(
            pool: $stack->pool,
            errorFormatter: $stack->errorFormatter,
            nullableAsType: $configuration->nullableAsType,
            emptyArrayStrategy: $configuration->emptyArrayStrategy,
            dialectProfile: $configuration->dialectProfile,
        ));
    }

    private function readProperty(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object, $name);

        return $property->getValue($object);
    }
}
