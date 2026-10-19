<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Example implements JsonSerializable
{
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public mixed $value = null,
        public mixed $dataValue = null,
        public mixed $serializedValue = null,
        public ?string $externalValue = null,
        public ?string $serializedExample = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('summary', 'summary'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('value', 'value'),
            new FieldMetadata('dataValue', 'dataValue'),
            new FieldMetadata('serializedValue', 'serializedValue'),
            new FieldMetadata('externalValue', 'externalValue'),
            new FieldMetadata('serializedExample', 'serializedExample'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
