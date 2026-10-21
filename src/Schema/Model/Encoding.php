<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
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

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('contentType', 'contentType'),
            new FieldMetadata('headers', 'headers'),
            new FieldMetadata('style', 'style'),
            new FieldMetadata('explode', 'explode'),
            new FieldMetadata('allowReserved', 'allowReserved'),
            new FieldMetadata('encoding', 'encoding'),
            new FieldMetadata('prefixEncoding', 'prefixEncoding'),
            new FieldMetadata('itemEncoding', 'itemEncoding'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
