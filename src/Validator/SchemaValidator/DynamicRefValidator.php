<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\JsonContainer;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;

use function is_array;
use function is_scalar;
use function str_starts_with;
use function strpos;
use function sprintf;
use function substr;
use function assert;

final readonly class DynamicRefValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private const string NO_DOCUMENT_REASON = 'Dynamic reference cannot be resolved without a document';

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->dynamicRef;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->dynamicRef) {
            return;
        }

        $target = $this->resolveOnEncounter($schema, $context);
        $instance = $this->assertValidatable($data);

        if (null === $context) {
            $this->rootValidator()->validate($instance, $target);

            return;
        }

        $this->validateTarget($instance, $target, $context);
    }

    private function assertValidatable(mixed $data): JsonContainer|array|int|string|float|bool|null
    {
        if (null === $data || is_scalar($data) || is_array($data) || $data instanceof JsonContainer) {
            return $data;
        }

        throw new InvalidDataTypeException(
            sprintf('Data must be array, int, string, float or bool, %s given', TypeFormatter::format($data)),
        );
    }

    private function resolveOnEncounter(Schema $schema, ?ValidationContext $context): Schema
    {
        $dynamicRef = $schema->dynamicRef;

        assert(null !== $dynamicRef);

        $initial = $this->resolveReference($dynamicRef, $this->scopeBaseUri($context));
        $fragment = $this->plainNameFragment($dynamicRef);

        if (null === $fragment || $fragment !== $initial->dynamicAnchor) {
            return $initial;
        }

        $outermost = $this->findOutermostDynamicAnchor($fragment, $context);

        return $outermost ?? $initial;
    }

    private function validateTarget(
        mixed $data,
        Schema $target,
        ValidationContext $context,
    ): void {
        if (false === $context->enterDynamicRefTarget($target, $context->path())) {
            return;
        }

        try {
            $this->rootValidator()->validateWithContext($data, $target, $context);
        } finally {
            $context->leaveDynamicRefTarget($target);
        }
    }

    private function resolveReference(string $dynamicRef, ?string $baseUri): Schema
    {
        $ref = $this->absolutize($dynamicRef, $baseUri);

        try {
            return $this->resolveInDocument($ref);
        } catch (UnresolvableRefException $exception) {
            return $this->resolveAcrossRegisteredDocuments($dynamicRef, $exception);
        }
    }

    private function resolveInDocument(string $ref): Schema
    {
        return $this->dependencies->refResolver->resolve($ref, $this->requireDocument());
    }

    private function absolutize(string $dynamicRef, ?string $baseUri): string
    {
        if (null === $baseUri) {
            return $dynamicRef;
        }

        return $this->dependencies->refResolver->combineUris($baseUri, $dynamicRef);
    }

    private function resolveAcrossRegisteredDocuments(
        string $dynamicRef,
        UnresolvableRefException $previous,
    ): Schema {
        if (null === $this->plainNameFragment($dynamicRef)) {
            throw $previous;
        }

        $document = $this->dependencies->document;

        if (null !== $document) {
            $resolved = $this->resolveOrNull($dynamicRef, $document);

            if (null !== $resolved) {
                return $resolved;
            }
        }

        foreach ($this->registeredDocuments() as $registeredDocument) {
            $resolved = $this->resolveOrNull($dynamicRef, $registeredDocument);

            if (null !== $resolved) {
                return $resolved;
            }
        }

        throw $previous;
    }

    private function resolveOrNull(string $ref, OpenApiDocument $document): ?Schema
    {
        try {
            return $this->dependencies->refResolver->resolve($ref, $document);
        } catch (UnresolvableRefException) {
            return null;
        }
    }

    /** @return list<OpenApiDocument> */
    private function registeredDocuments(): array
    {
        $refResolver = $this->dependencies->refResolver;

        if (false === $refResolver instanceof RefResolver) {
            return [];
        }

        return $refResolver->registeredDocuments();
    }

    private function findOutermostDynamicAnchor(string $name, ?ValidationContext $context): ?Schema
    {
        if (null === $context) {
            return null;
        }

        return $this->dependencies->refResolver->findDynamicAnchorInScope(
            $context->dynamicScope(),
            $name,
            $this->requireDocument(),
        );
    }

    /** @return ?string */
    private function scopeBaseUri(?ValidationContext $context): ?string
    {
        if (null === $context) {
            return null;
        }

        return $this->dependencies->refResolver->scopeBaseUri(
            $context->dynamicScope(),
            $this->requireDocument(),
        );
    }

    private function plainNameFragment(string $dynamicRef): ?string
    {
        $position = strpos($dynamicRef, '#');

        if (false === $position) {
            return null;
        }

        $fragment = substr($dynamicRef, $position + 1);

        if ('' === $fragment || str_starts_with($fragment, '/')) {
            return null;
        }

        return $fragment;
    }

    private function requireDocument(): OpenApiDocument
    {
        return $this->dependencies->document
            ?? throw new UnresolvableRefException('', self::NO_DOCUMENT_REASON);
    }

    private function rootValidator(): SchemaValidatorWithContext
    {
        $document = $this->requireDocument();

        return $this->dependencies->rootSchemaValidator(
            $document,
            new ValidatorConfiguration(dialectProfile: DialectProfile::fromOpenApiVersion($document->openapi)),
        );
    }
}
