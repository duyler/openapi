<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\TypeFormatter;

use LogicException;

use function array_key_exists;
use function end;
use function explode;
use function is_array;
use function is_string;

final readonly class DiscriminatorValidator
{
    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {}

    public function validate(
        array|int|string|float|bool $data,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath = '/',
    ): void {
        $discriminator = $schema->discriminator;

        if (null === $discriminator) {
            return;
        }

        if (null === $discriminator->propertyName) {
            $this->validateWithoutPropertyName($data, $discriminator, $document);

            return;
        }

        $this->validateWithPropertyName($data, $discriminator, $schema, $document, $dataPath);
    }

    private function validateWithPropertyName(
        array|int|string|float|bool $data,
        Discriminator $discriminator,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath,
    ): void {
        $propertyName = $discriminator->propertyName
            ?? throw new LogicException('Discriminator property name must be set when calling validateWithPropertyName');
        $value = $this->extractValue($data, $propertyName, $dataPath);
        $targetSchema = $this->resolveSchema($value, $discriminator, $schema, $document, $dataPath);

        $this->validateAgainstSchema($data, $targetSchema, $document);
    }

    private function validateWithoutPropertyName(
        array|int|string|float|bool $data,
        Discriminator $discriminator,
        OpenApiDocument $document,
    ): void {
        if (null === $discriminator->defaultMapping) {
            return;
        }

        $targetSchema = $this->dependencies->refResolver->resolve($discriminator->defaultMapping, $document);
        $this->validateAgainstSchema($data, $targetSchema, $document);
    }

    private function buildPath(string $basePath, string $segment): string
    {
        if ('/' === $basePath) {
            return '/' . $segment;
        }

        return rtrim($basePath, '/') . '/' . $segment;
    }

    private function extractValue(mixed $data, string $propertyName, string $dataPath): string
    {
        if (false === is_array($data)) {
            throw new DiscriminatorMismatchException(
                expectedType: 'object',
                actualType: TypeFormatter::format($data),
                propertyName: $propertyName,
                dataPath: $dataPath,
            );
        }

        if (false === array_key_exists($propertyName, $data)) {
            throw new MissingDiscriminatorPropertyException(
                propertyName: $propertyName,
                dataPath: $dataPath,
            );
        }

        $value = $data[$propertyName];

        if (false === is_string($value)) {
            throw new InvalidDiscriminatorValueException(
                propertyName: $propertyName,
                expectedType: 'string',
                actualType: TypeFormatter::format($value),
                dataPath: $this->buildPath($dataPath, $propertyName),
            );
        }

        return $value;
    }

    private function resolveSchema(
        string $value,
        Discriminator $discriminator,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath,
    ): Schema {
        $mapping = $discriminator->mapping ?? [];

        if (isset($mapping[$value])) {
            return $this->dependencies->refResolver->resolve($mapping[$value], $document);
        }

        return $this->findMatchingSchema($value, $discriminator, $schema, $document, $dataPath);
    }

    private function findMatchingSchema(
        string $value,
        Discriminator $discriminator,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath,
    ): Schema {
        $schemas = $schema->oneOf ?? $schema->anyOf ?? $schema->allOf ?? [];

        foreach ($schemas as $candidateSchema) {
            if (null !== $candidateSchema->ref) {
                $implicitName = $this->extractSchemaName($candidateSchema->ref);

                if ($implicitName === $value) {
                    return $this->dependencies->refResolver->resolve($candidateSchema->ref, $document);
                }
            }

            if (null !== $candidateSchema->oneOf || null !== $candidateSchema->anyOf || null !== $candidateSchema->allOf) {
                return $this->findMatchingSchema($value, $discriminator, $candidateSchema, $document, $dataPath);
            }
        }

        if (null !== $discriminator->defaultMapping) {
            return $this->dependencies->refResolver->resolve($discriminator->defaultMapping, $document);
        }

        throw new UnknownDiscriminatorValueException(
            value: $value,
            schema: $schema,
            dataPath: $dataPath,
        );
    }

    private function extractSchemaName(string $ref): string
    {
        $parts = explode('/', $ref);

        return end($parts);
    }

    private function validateAgainstSchema(
        mixed $data,
        Schema $schema,
        OpenApiDocument $document,
    ): void {
        /** @var array<array-key, mixed> $data */
        $rootValidator = $this->dependencies->rootSchemaValidator($document, $this->configuration);
        $context = ValidationContext::create(
            $this->dependencies->pool,
            $this->dependencies->errorFormatter,
            $this->configuration->nullableAsType,
            $this->configuration->emptyArrayStrategy,
        );
        $rootValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
    }
}
