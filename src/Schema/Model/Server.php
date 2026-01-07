<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Server implements JsonSerializable
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public string $url,
        public ?string $description = null,
        public ?array $variables = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'url' => $this->url,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->variables !== null) {
            $data['variables'] = $this->variables;
        }

        return $data;
    }
}
