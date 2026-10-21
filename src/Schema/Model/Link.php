<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use JsonSerializable;
use Override;

final readonly class Link implements JsonSerializable
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public ?string $operationRef = null,
        public ?string $ref = null,
        public ?string $description = null,
        public ?string $operationId = null,
        public ?array $parameters = null,
        public ?RequestBody $requestBody = null,
        public ?Server $server = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('operationRef', 'operationRef'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('operationId', 'operationId'),
            new FieldMetadata('parameters', 'parameters'),
            new FieldMetadata('requestBody', 'requestBody'),
            new FieldMetadata('server', 'server'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $refData = new RefSerializer()->toWireForm($this->ref, null, null) ?? [];

        return $refData + ModelWireSerializer::toArray($this, self::wireFields());
    }
}
