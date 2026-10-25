<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Schema\Model\Schema;

final readonly class BodyParser
{
    private const string JSON_API_MEDIA_TYPE = 'application/vnd.api+json';

    public function __construct(
        private readonly JsonBodyParser $jsonParser,
        private readonly FormBodyParser $formParser,
        private readonly MultipartBodyParser $multipartParser,
        private readonly TextBodyParser $textParser,
        private readonly XmlBodyParser $xmlParser,
    ) {}

    public function multipartParser(): MultipartBodyParser
    {
        return $this->multipartParser;
    }

    public function xmlParser(): XmlBodyParser
    {
        return $this->xmlParser;
    }

    public function parse(
        string $body,
        string $mediaType,
        string $fullContentType = '',
        ?Schema $schema = null,
    ): array|int|string|float|bool|null {
        return match ($mediaType) {
            'application/json', self::JSON_API_MEDIA_TYPE => $this->jsonParser->parse($body),
            'application/x-www-form-urlencoded' => $this->formParser->parse($body),
            'multipart/form-data' => $this->multipartParser->parse($body, $fullContentType),
            'text/plain', 'text/html', 'text/csv' => $this->textParser->parse($body),
            'application/xml', 'text/xml' => $this->xmlParser->parse($body, $schema),
            default => $body,
        };
    }
}
