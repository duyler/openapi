<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\ValidatorPool;

use function array_key_exists;
use function is_array;
use function is_string;
use function assert;

readonly class DiscriminatorValidator
{
    public function __construct(
        private readonly RefResolverInterface $refResolver,
        private readonly ValidatorPool $pool,
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

        if (null !== $discriminator->propertyName) {
            $this->validateWithPropertyName($data, $discriminator, $schema, $document, $dataPath);
        } else {
            $this->validateWithoutPropertyName($data, $discriminator, $document, $dataPath);
        }
    }

    private function validateWithPropertyName(
        array|int|string|float|bool $data,
        Discriminator $discriminator,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath,
    ): void {
        $propertyName = $discriminator->propertyName;
        assert(null !== $propertyName);
        $value = $this->extractValue($data, $propertyName, $dataPath);
        $targetSchema = $this->resolveSchema($value, $discriminator, $schema, $document, $dataPath);

        $propertyPath = $this->buildPath($dataPath, $propertyName);
        $this->validateAgainstSchema($data, $targetSchema, $document, $propertyPath);
    }

    private function validateWithoutPropertyName(
        array|int|string|float|bool $data,
        Discriminator $discriminator,
        OpenApiDocument $document,
        string $dataPath,
    ): void {
        if (null === $discriminator->defaultMapping) {
            return;
        }

        $targetSchema = $this->refResolver->resolve($discriminator->defaultMapping, $document);
        $this->validateAgainstSchema($data, $targetSchema, $document, $dataPath);
    }

    private function buildPath(string $basePath, string $segment): string
    {
        if ($basePath === '/') {
            return '/' . $segment;
        }

        return rtrim($basePath, '/') . '/' . $segment;
    }

    private function extractValue(mixed $data, string $propertyName, string $dataPath): string
    {
        if (false === is_array($data)) {
            throw new DiscriminatorMismatchException(
                expectedType: 'object',
                actualType: get_debug_type($data),
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
                actualType: get_debug_type($value),
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
            return $this->refResolver->resolve($mapping[$value], $document);
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
        $schemas = $schema->oneOf ?? $schema->anyOf ?? [];

        foreach ($schemas as $candidateSchema) {
            if (null === $candidateSchema->ref) {
                continue;
            }

            $refPath = $candidateSchema->ref;

            $resolvedSchema = $this->refResolver->resolve($refPath, $document);

            if ($this->schemaMatchesValue($resolvedSchema, $value)) {
                return $resolvedSchema;
            }
        }

        if (null !== $discriminator->defaultMapping) {
            return $this->refResolver->resolve($discriminator->defaultMapping, $document);
        }

        throw new UnknownDiscriminatorValueException(
            value: $value,
            schema: $schema,
            dataPath: $dataPath,
        );
    }

    private function schemaMatchesValue(Schema $schema, string $value): bool
    {
        if (null !== $schema->title) {
            return $schema->title === $value;
        }

        return false;
    }

    private function validateAgainstSchema(
        mixed $data,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath,
    ): void {
        /** @var array<array-key, mixed> $data */
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);
        $validator->validate($data, $schema, useDiscriminator: false);
    }
}
