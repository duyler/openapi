<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Error\ValidationContext;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class ResponseBodyValidatorWithContext
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;

    public function __construct(
        private readonly ValidatorPool $pool,
        OpenApiDocument $document,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly JsonBodyParser $jsonParser = new JsonBodyParser(),
        private readonly FormBodyParser $formParser = new FormBodyParser(),
        private readonly MultipartBodyParser $multipartParser = new MultipartBodyParser(),
        private readonly TextBodyParser $textParser = new TextBodyParser(),
        private readonly XmlBodyParser $xmlParser = new XmlBodyParser(),
        private readonly ResponseTypeCoercer $typeCoercer = new ResponseTypeCoercer(),
        private readonly bool $coercion = false,
        private readonly bool $nullableAsType = true,
    ) {
        $formatRegistry = BuiltinFormats::create();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $formatRegistry);

        $refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $refResolver, $document, $this->nullableAsType);
    }

    public function validate(
        string $body,
        string $contentType,
        ?Content $content,
    ): void {
        if (null === $content) {
            return;
        }

        $mediaType = $this->negotiator->getMediaType($contentType);
        $mediaTypeSchema = $content->mediaTypes[$mediaType] ?? null;

        if (null === $mediaTypeSchema) {
            return;
        }

        $parsedBody = $this->parseBody($body, $mediaType);

        if ($this->coercion && null !== $mediaTypeSchema->schema) {
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true, $this->nullableAsType);
            $parsedBody = $this->ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $mediaTypeSchema->schema) {
            $schema = $mediaTypeSchema->schema;
            $hasDiscriminator = null !== $schema->discriminator || $this->schemaHasDiscriminator($schema);

            $context = ValidationContext::create($this->pool, $this->nullableAsType);

            if ($hasDiscriminator) {
                $this->contextSchemaValidator->validate($parsedBody, $schema);
            } else {
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);
            }
        }
    }

    private function schemaHasDiscriminator(Schema $schema): bool
    {
        if (null !== $schema->discriminator) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $property) {
                if ($this->schemaHasDiscriminator($property)) {
                    return true;
                }
            }
        }

        if (null !== $schema->items) {
            return $this->schemaHasDiscriminator($schema->items);
        }

        if (null !== $schema->oneOf) {
            foreach ($schema->oneOf as $subSchema) {
                if ($this->schemaHasDiscriminator($subSchema)) {
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
