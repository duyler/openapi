<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Override;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

readonly class RequestBodyValidator implements RequestBodyValidatorInterface
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ContentTypeNegotiator $negotiator,
        private readonly JsonBodyParser $jsonParser,
        private readonly FormBodyParser $formParser,
        private readonly MultipartBodyParser $multipartParser,
        private readonly TextBodyParser $textParser,
        private readonly XmlBodyParser $xmlParser,
    ) {}

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

        if (null !== $content->schema) {
            $this->schemaValidator->validate($parsedBody, $content->schema);
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
}
