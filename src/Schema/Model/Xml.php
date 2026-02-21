<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

use function in_array;

final readonly class Xml implements JsonSerializable
{
    public const array VALID_NODE_TYPES = ['element', 'attribute', 'text', 'cdata', 'none'];

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
        return in_array($nodeType, self::VALID_NODE_TYPES, true);
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
