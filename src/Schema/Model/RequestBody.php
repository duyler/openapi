<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use JsonSerializable;
use Override;

final readonly class RequestBody implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $description = null,
        public ?Content $content = null,
        public bool $required = false,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('description', 'description'),
            new FieldMetadata('content', 'content'),
            new FieldMetadata('required', 'required', accessor: static fn(self $model): ?bool => $model->required ? true : null),
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
