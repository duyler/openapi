<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use JsonSerializable;
use Override;

final readonly class Parameter implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $name = null,
        public ?string $in = null,
        public ?string $description = null,
        public bool $required = false,
        public bool $deprecated = false,
        public bool $allowEmptyValue = false,
        public ?string $style = null,
        public bool $explode = false,
        public bool $allowReserved = false,
        public ?Schema $schema = null,
        public ?array $examples = null,
        public ?Example $example = null,
        public ?Content $content = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('name', 'name'),
            new FieldMetadata('in', 'in'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('required', 'required', accessor: static fn(self $model): ?bool => $model->required ? true : null),
            new FieldMetadata('deprecated', 'deprecated', accessor: static fn(self $model): ?bool => $model->deprecated ? true : null),
            new FieldMetadata('allowEmptyValue', 'allowEmptyValue', accessor: static fn(self $model): ?bool => $model->allowEmptyValue ? true : null),
            new FieldMetadata('style', 'style'),
            new FieldMetadata('explode', 'explode', accessor: static fn(self $model): ?bool => $model->explode ? true : null),
            new FieldMetadata('allowReserved', 'allowReserved', accessor: static fn(self $model): ?bool => $model->allowReserved ? true : null),
            new FieldMetadata('schema', 'schema'),
            new FieldMetadata('example', 'example'),
            new FieldMetadata('examples', 'examples'),
            new FieldMetadata('content', 'content'),
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
