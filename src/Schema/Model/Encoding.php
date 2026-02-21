<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Encoding implements JsonSerializable
{
    /**
     * @param array<string, Encoding>|null $encoding
     * @param array<int, Encoding>|null $prefixEncoding
     */
    public function __construct(
        public ?string $contentType = null,
        public ?Headers $headers = null,
        public ?string $style = null,
        public ?bool $explode = null,
        public ?bool $allowReserved = null,
        public ?array $encoding = null,
        public ?array $prefixEncoding = null,
        public ?Encoding $itemEncoding = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $result = [];

        if (null !== $this->contentType) {
            $result['contentType'] = $this->contentType;
        }
        if (null !== $this->headers) {
            $result['headers'] = $this->headers;
        }
        if (null !== $this->style) {
            $result['style'] = $this->style;
        }
        if (null !== $this->explode) {
            $result['explode'] = $this->explode;
        }
        if (null !== $this->allowReserved) {
            $result['allowReserved'] = $this->allowReserved;
        }
        if (null !== $this->encoding) {
            $result['encoding'] = $this->encoding;
        }
        if (null !== $this->prefixEncoding) {
            $result['prefixEncoding'] = $this->prefixEncoding;
        }
        if (null !== $this->itemEncoding) {
            $result['itemEncoding'] = $this->itemEncoding;
        }

        return $result;
    }
}
