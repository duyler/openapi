<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Header implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public ?string $description = null,
        public bool $required = false,
        public bool $deprecated = false,
        public bool $allowEmptyValue = false,
        public ?Schema $schema = null,
        public mixed $example = null,
        public ?array $examples = null,
        public ?Content $content = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('description', 'description'),
            new FieldMetadata('required', 'required', accessor: static fn(self $model): ?bool => $model->required ? true : null),
            new FieldMetadata('deprecated', 'deprecated', accessor: static fn(self $model): ?bool => $model->deprecated ? true : null),
            new FieldMetadata('allowEmptyValue', 'allowEmptyValue', accessor: static fn(self $model): ?bool => $model->allowEmptyValue ? true : null),
            new FieldMetadata('schema', 'schema'),
            new FieldMetadata('example', 'example'),
            new FieldMetadata('examples', 'examples'),
            new FieldMetadata('content', 'content'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
