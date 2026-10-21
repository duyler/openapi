<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class MediaType implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     * @param array<string, Encoding>|null $encoding
     * @param array<int, Encoding>|null $prefixEncoding
     */
    public function __construct(
        public ?Schema $schema = null,
        public ?Schema $itemSchema = null,
        public ?array $encoding = null,
        public ?Encoding $itemEncoding = null,
        public ?array $prefixEncoding = null,
        public ?array $examples = null,
        public ?Example $example = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('schema', 'schema'),
            new FieldMetadata('itemSchema', 'itemSchema'),
            new FieldMetadata('encoding', 'encoding'),
            new FieldMetadata('itemEncoding', 'itemEncoding'),
            new FieldMetadata('prefixEncoding', 'prefixEncoding'),
            new FieldMetadata('example', 'example'),
            new FieldMetadata('examples', 'examples'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
