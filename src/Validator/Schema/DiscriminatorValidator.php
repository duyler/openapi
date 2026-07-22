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

    /**
     * @param array<array-key, mixed>|int|string|float|bool $data
     *
     * When a non-null $context is supplied (canonical SchemaValidatorWithContext
     * path), evaluated-property / evaluated-item annotations produced by the
     * discriminator target sub-validation are merged back into it via
     * forkForBranch + mergeChildAnnotations, so parent schemas declaring
     * unevaluatedProperties:false / unevaluatedItems:false honour properties
     * already validated by the target schema (JSON Schema 2020-12 §10.3.4).
     *
     * The optional default preserves backward compatibility for direct unit
     * callers; null triggers a fresh ValidationContext that mirrors the
     * pre-fix behaviour (annotations isolated and discarded).
     */
    public function validate(
        array|int|string|float|bool $data,
        Schema $schema,
        OpenApiDocument $document,
        string $dataPath = '/',
        ?ValidationContext $context = null,
    ): void {
        $discriminator = $schema->discriminator;

        if (null === $discriminator) {
            return;
        }

        $validationContext = $context ?? ValidationContext::create(
            $this->dependencies->pool,
            $this->dependencies->errorFormatter,
            $this->configuration->nullableAsType,
            $this->configuration->emptyArrayStrategy,
        );

        if (null === $discriminator->propertyName) {
            $this->validateWithoutPropertyName($data, $discriminator, $document, $validationContext);

            return;
        }

        $value = $this->extractValue($data, $discriminator->propertyName, $dataPath);
        $targetSchema = $this->resolveSchema($value, $discriminator, $schema, $document, $dataPath);

        $this->validateAgainstSchema($data, $targetSchema, $document, $validationContext);
    }

    private function validateWithoutPropertyName(
        array|int|string|float|bool $data,
        Discriminator $discriminator,
        OpenApiDocument $document,
        ValidationContext $context,
    ): void {
        if (null === $discriminator->defaultMapping) {
            return;
        }

        $targetSchema = $this->dependencies->refResolver->resolve($discriminator->defaultMapping, $document);
        $this->validateAgainstSchema($data, $targetSchema, $document, $context);
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
        // R4-CORRECTNESS-002/015: enumerate candidates from every composition array
        // simultaneously (JSON Schema 2020-12 §10.2.1.1), not just the first non-null.
        $candidates = [
            ...($schema->oneOf ?? []),
            ...($schema->anyOf ?? []),
            ...($schema->allOf ?? []),
        ];

        foreach ($candidates as $candidateSchema) {
            if (null !== $candidateSchema->ref) {
                $implicitName = $this->extractSchemaName($candidateSchema->ref);

                if ($implicitName === $value) {
                    return $this->dependencies->refResolver->resolve($candidateSchema->ref, $document);
                }
            }

            if (null !== $candidateSchema->oneOf || null !== $candidateSchema->anyOf || null !== $candidateSchema->allOf) {
                try {
                    return $this->findMatchingSchema($value, $discriminator, $candidateSchema, $document, $dataPath);
                } catch (UnknownDiscriminatorValueException) {
                    continue;
                }
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
        ValidationContext $context,
    ): void {
        /** @var array<array-key, mixed> $data */
        $rootValidator = $this->dependencies->rootSchemaValidator($document, $this->configuration);

        $child = $context->forkForBranch();
        $rootValidator->validateWithContextIgnoringDiscriminator($data, $schema, $child);
        $context->mergeChildAnnotations($child);
    }
}
