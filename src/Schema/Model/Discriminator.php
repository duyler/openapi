<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Discriminator implements JsonSerializable
{
    /**
     * @param array<string, string> $mapping
     */
    public function __construct(
        public ?string $propertyName = null,
        public ?array $mapping = null,
        public ?string $defaultMapping = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('propertyName', 'propertyName'),
            new FieldMetadata('mapping', 'mapping'),
            new FieldMetadata('defaultMapping', 'defaultMapping'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
