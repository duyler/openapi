<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use JsonSerializable;
use Override;

final readonly class Response implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?Headers $headers = null,
        public ?Content $content = null,
        public ?Links $links = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('summary', 'summary'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('headers', 'headers'),
            new FieldMetadata('content', 'content'),
            new FieldMetadata('links', 'links'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $refData = new RefSerializer()->toWireForm($this->ref, $this->refSummary, $this->refDescription);

        if (null !== $refData) {
            return $refData;
        }

        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
