<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Model\Enum\XmlNodeType;
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

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        if (null !== $this->namespace) {
            $data['namespace'] = $this->namespace;
        }

        if (null !== $this->prefix) {
            $data['prefix'] = $this->prefix;
        }

        if (null !== $this->attribute) {
            $data['attribute'] = $this->attribute;
        }

        if (null !== $this->wrapped) {
            $data['wrapped'] = $this->wrapped;
        }

        if (null !== $this->nodeType) {
            $data['nodeType'] = $this->nodeType;
        }

        return $data;
    }
}
