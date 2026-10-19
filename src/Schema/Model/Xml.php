<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Model\Enum\XmlNodeType;
use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Xml implements JsonSerializable
{
    /** @var list<string> */
    public const array VALID_NODE_TYPES = [
        XmlNodeType::Element->value,
        XmlNodeType::Attribute->value,
        XmlNodeType::Text->value,
        XmlNodeType::Cdata->value,
        XmlNodeType::None->value,
    ];

    public function __construct(
        public ?string $name = null,
        public ?string $namespace = null,
        public ?string $prefix = null,
        public ?bool $attribute = null,
        public ?bool $wrapped = null,
        public ?string $nodeType = null,
    ) {}

    public static function isValidNodeType(string $nodeType): bool
    {
        return null !== XmlNodeType::tryFrom($nodeType);
    }

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('name', 'name'),
            new FieldMetadata('namespace', 'namespace'),
            new FieldMetadata('prefix', 'prefix'),
            new FieldMetadata('attribute', 'attribute'),
            new FieldMetadata('wrapped', 'wrapped'),
            new FieldMetadata('nodeType', 'nodeType'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
