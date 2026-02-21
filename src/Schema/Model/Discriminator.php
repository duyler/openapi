<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Discriminator implements JsonSerializable
{
    /**
     * @param array<string, string> $mapping
     */
    public function __construct(
        public ?string $propertyName = null,
        public ?array $mapping = null,
        public ?string $defaultMapping = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->propertyName) {
            $data['propertyName'] = $this->propertyName;
        }

        if (null !== $this->mapping) {
            $data['mapping'] = $this->mapping;
        }

        if (null !== $this->defaultMapping) {
            $data['defaultMapping'] = $this->defaultMapping;
        }

        return $data;
    }
}
