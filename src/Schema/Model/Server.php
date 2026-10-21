<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Server implements JsonSerializable
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public string $url,
        public ?string $description = null,
        public ?array $variables = null,
        public ?string $name = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('url', 'url'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('variables', 'variables'),
            new FieldMetadata('name', 'name'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
