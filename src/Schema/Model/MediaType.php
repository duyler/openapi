<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class MediaType implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public ?Schema $schema = null,
        public ?string $encoding = null,
        public ?array $examples = null,
        public ?Example $example = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->schema !== null) {
            $data['schema'] = $this->schema;
        }

        if ($this->encoding !== null) {
            $data['encoding'] = $this->encoding;
        }

        if ($this->example !== null) {
            $data['example'] = $this->example;
        }

        if ($this->examples !== null) {
            $data['examples'] = $this->examples;
        }

        return $data;
    }
}
