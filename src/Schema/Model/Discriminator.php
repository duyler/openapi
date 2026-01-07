<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Discriminator implements JsonSerializable
{
    /**
     * @param array<string, string> $mapping
     */
    public function __construct(
        public string $propertyName,
        public ?array $mapping = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'propertyName' => $this->propertyName,
        ];

        if ($this->mapping !== null) {
            $data['mapping'] = $this->mapping;
        }

        return $data;
    }
}
