<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use function array_is_list;
use function assert;

/**
 * Canonical representation of a JSON container whose PHP array shape
 * would be ambiguous: an empty object, an empty array, or an object
 * with sequential integer-like property names.
 */
final readonly class JsonContainer
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public readonly JsonContainerKind $kind,
        public readonly array $values,
    ) {
        if (JsonContainerKind::Array === $kind) {
            assert(array_is_list($values));
        }
    }

    public function isObject(): bool
    {
        return JsonContainerKind::Object === $this->kind;
    }

    public function isArray(): bool
    {
        return JsonContainerKind::Array === $this->kind;
    }
}
