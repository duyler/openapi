<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

readonly class BodyParser
{
    public function __construct(
        private readonly JsonBodyParser $jsonParser,
        private readonly FormBodyParser $formParser,
        private readonly MultipartBodyParser $multipartParser,
        private readonly TextBodyParser $textParser,
        private readonly XmlBodyParser $xmlParser,
    ) {}

    public function parse(string $body, string $mediaType): array|int|string|float|bool|null
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
