<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\FormatAssertionMode;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Validation\ValidationStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

use function assert;

final class FormatAssertionModeTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Format Assertion Mode API
  version: 1.0.0
paths: {}
components:
  schemas:
    Email:
      type: string
      format: email
    Custom:
      type: string
      format: custom-format
YAML;

    #[Test]
    public function default_mode_fails_invalid_email(): void
    {
        $validator = $this->buildValidator();

        $this->expectException(InvalidFormatException::class);

        $validator->validateSchema('not-an-email', '#/components/schemas/Email');
    }

    #[Test]
    public function annotation_mode_passes_invalid_email(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormatAssertionMode(FormatAssertionMode::Annotation)
            ->build();

        $validator->validateSchema('not-an-email', '#/components/schemas/Email');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function annotation_mode_records_format_annotation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormatAssertionMode(FormatAssertionMode::Annotation)
            ->build();

        $annotations = $this->collectAnnotations($validator, 'Email', 'not-an-email');

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $annotations['format'] ?? [],
        );
    }

    #[Test]
    public function explicit_assertion_mode_fails_invalid_email(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormatAssertionMode(FormatAssertionMode::Assertion)
            ->build();

        $this->expectException(InvalidFormatException::class);

        $validator->validateSchema('not-an-email', '#/components/schemas/Email');
    }

    #[Test]
    public function assertion_mode_collects_format_annotation_for_valid_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withFormatAssertionMode(FormatAssertionMode::Assertion)
            ->build();

        $annotations = $this->collectAnnotations($validator, 'Email', 'user@example.com');

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $annotations['format'] ?? [],
        );
    }

    #[Test]
    public function annotation_mode_with_strict_formats_passes_unknown_format(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableStrictFormats()
            ->withFormatAssertionMode(FormatAssertionMode::Annotation)
            ->build();

        $annotations = $this->collectAnnotations($validator, 'Custom', 'any-value');

        $this->assertSame(
            [['path' => '/', 'value' => 'custom-format']],
            $annotations['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_with_strict_formats_throws_unknown_format(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableStrictFormats()
            ->withFormatAssertionMode(FormatAssertionMode::Assertion)
            ->build();

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unknown format "custom-format"');

        $validator->validateSchema('any-value', '#/components/schemas/Custom');
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    /**
     * @return array<string, list<array{path: string, value: mixed}>>
     */
    private function collectAnnotations(
        OpenApiValidatorInterface $validator,
        string $schemaName,
        mixed $data,
    ): array {
        assert($validator instanceof OpenApiValidator);

        $stack = $this->extractValidationStack($validator);
        $schema = $validator->getDocument()->components?->schemas[$schemaName] ?? null;

        assert($schema instanceof Schema);

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $validator->getPool(),
            errorFormatter: $stack->errorFormatter,
        ));

        $stack->schemaValidatorWithContext->validateWithContext($data, $schema, $context);

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
