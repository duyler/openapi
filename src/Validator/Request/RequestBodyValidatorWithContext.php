<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;

use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;
use Duyler\OpenApi\Schema\Model\Schema;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RefResolverInterface $refResolver;
    private RequestBodyCoercer $coercer;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly JsonBodyParser $jsonParser = new JsonBodyParser(),
        private readonly FormBodyParser $formParser = new FormBodyParser(),
        private readonly MultipartBodyParser $multipartParser = new MultipartBodyParser(),
        private readonly TextBodyParser $textParser = new TextBodyParser(),
        private readonly XmlBodyParser $xmlParser = new XmlBodyParser(),
        private readonly bool $nullableAsType = true,
        private readonly bool $coercion = false,
    ) {
        $formatRegistry = BuiltinFormats::create();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $formatRegistry);

        $this->refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->nullableAsType);
        $this->coercer = new RequestBodyCoercer();
    }

    #[Override]
    public function validate(
        string $body,
        string $contentType,
        ?RequestBody $requestBody,
    ): void {
        if (null === $requestBody) {
            return;
        }

        if (null === $requestBody->content) {
            return;
        }

        $mediaType = $this->negotiator->getMediaType($contentType);
        $content = $requestBody->content->mediaTypes[$mediaType] ?? null;

        if (null === $content) {
            throw new UnsupportedMediaTypeException($mediaType, array_keys($requestBody->content->mediaTypes));
        }

        $parsedBody = $this->parseBody($body, $mediaType);

        if ($this->coercion && null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->refResolver->resolve($schema->ref, $this->document);
            }

            $parsedBody = $this->coercer->coerce($parsedBody, $schema, true, true, $this->nullableAsType);
            $parsedBody = $this->ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->refResolver->resolve($schema->ref, $this->document);
            }

            $hasDiscriminator = null !== $schema->discriminator || $this->schemaHasDiscriminator($schema);

            if ($hasDiscriminator) {
                $this->contextSchemaValidator->validate($parsedBody, $schema);
            } else {
                $context = ValidationContext::create($this->pool, $this->nullableAsType);
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);
            }
        }
    }

    private function schemaHasDiscriminator(Schema $schema, array &$visited = []): bool
    {
        $schemaId = spl_object_id($schema);

        if (isset($visited[$schemaId])) {
            return false;
        }

        $visited[$schemaId] = true;

        if (null !== $schema->ref) {
            $resolvedSchema = $this->refResolver->resolve($schema->ref, $this->document);
            return $this->schemaHasDiscriminator($resolvedSchema, $visited);
        }

        if (null !== $schema->discriminator) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $property) {
                if ($this->schemaHasDiscriminator($property, $visited)) {
                    return true;
                }
            }
        }

        if (null !== $schema->items) {
            return $this->schemaHasDiscriminator($schema->items, $visited);
        }

        if (null !== $schema->oneOf) {
            foreach ($schema->oneOf as $subSchema) {
                if ($this->schemaHasDiscriminator($subSchema, $visited)) {
                    return true;
                }
            }
        }

        if (null !== $schema->anyOf) {
            foreach ($schema->anyOf as $subSchema) {
                if ($this->schemaHasDiscriminator($subSchema, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseBody(string $body, string $mediaType): array|int|string|float|bool|null
    {
        return match ($mediaType) {
            'application/json' => $this->jsonParser->parse($body),
            'application/x-www-form-urlencoded' => $this->formParser->parse($body),
            'multipart/form-data' => $this->multipartParser->parse($body),
            'text/plain', 'text/html', 'text/csv' => $this->textParser->parse($body),
            'application/xml', 'text/xml' => $this->xmlParser->parse($body),
            default => $body,
        };
    }

    private function ensureValidType(mixed $value, bool $nullableAsType = true): array|int|string|float|bool|null
    {
        if (is_array($value)) {
            return $value;
        }

        if (null === $value && $nullableAsType) {
            return $value;
        }

        if (is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return (string) $value;
    }
}
