<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class MediaType implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     * @param array<string, Encoding>|null $encoding
     * @param array<int, Encoding>|null $prefixEncoding
     */
    public function __construct(
        public ?Schema $schema = null,
        public ?Schema $itemSchema = null,
        public ?array $encoding = null,
        public ?Encoding $itemEncoding = null,
        public ?array $prefixEncoding = null,
        public ?array $examples = null,
        public ?Example $example = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->schema) {
            $data['schema'] = $this->schema;
        }

        if (null !== $this->itemSchema) {
            $data['itemSchema'] = $this->itemSchema;
        }

        if (null !== $this->encoding) {
            $data['encoding'] = $this->encoding;
        }

        if (null !== $this->itemEncoding) {
            $data['itemEncoding'] = $this->itemEncoding;
        }

        if (null !== $this->prefixEncoding) {
            $data['prefixEncoding'] = $this->prefixEncoding;
        }

        if (null !== $this->example) {
            $data['example'] = $this->example;
        }

        if (null !== $this->examples) {
            $data['examples'] = $this->examples;
        }

        return $data;
    }
}
