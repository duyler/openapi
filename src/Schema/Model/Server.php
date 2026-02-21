<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Server implements JsonSerializable
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public string $url,
        public ?string $description = null,
        public ?array $variables = null,
        public ?string $name = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'url' => $this->url,
        ];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->variables) {
            $data['variables'] = $this->variables;
        }

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        return $data;
    }
}
