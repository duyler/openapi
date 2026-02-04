<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class ResponseBodyValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ContentTypeNegotiator $negotiator,
        private readonly JsonBodyParser $jsonParser,
        private readonly FormBodyParser $formParser,
        private readonly MultipartBodyParser $multipartParser,
        private readonly TextBodyParser $textParser,
        private readonly XmlBodyParser $xmlParser,
        private readonly ResponseTypeCoercer $typeCoercer,
        private readonly bool $coercion = false,
    ) {}

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
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true);
            $parsedBody = $this->ensureValidType($parsedBody);
        }

        if (null !== $mediaTypeSchema->schema) {
            $this->schemaValidator->validate($parsedBody, $mediaTypeSchema->schema);
        }
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

    private function ensureValidType(mixed $value): array|int|string|float|bool
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return (string) $value;
    }
}
