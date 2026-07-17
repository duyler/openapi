<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use WeakMap;

use function bin2hex;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function random_bytes;

/**
 * Per-instance scalar-enum lookup cache. Stores precompiled `array<string,true>`
 * HashSets keyed by Schema so the hot-path `EnumValidator::validate` resolves
 * scalar enums in O(1) instead of an O(N) linear scan.
 *
 * Lives outside the readonly `EnumValidator` class because `WeakMap::offsetSet`
 * mutates the property from Psalm's perspective even though the property
 * reference itself never changes after construction.
 */
final class EnumScalarCache
{
    /** @var WeakMap<Schema, bool> */
    private WeakMap $isScalarEnumCache;

    /** @var WeakMap<Schema, array<string, true>> */
    private WeakMap $scalarEnumCache;

    public function __construct()
    {
        /** @var WeakMap<Schema, bool> $isScalarEnumCache */
        $isScalarEnumCache = new WeakMap();
        $this->isScalarEnumCache = $isScalarEnumCache;

        /** @var WeakMap<Schema, array<string, true>> $scalarEnumCache */
        $scalarEnumCache = new WeakMap();
        $this->scalarEnumCache = $scalarEnumCache;
    }

    /**
     * True when every value in `$schema->enum` is a JSON scalar
     * (null/int/float/string/bool) AND `$data` is itself a scalar — the
     * conditions under which the precompiled HashSet is a sound replacement
     * for the linear-scan fallback.
     */
    public function isScalarLookupEligible(Schema $schema, mixed $data): bool
    {
        if (!$this->isScalarEnum($schema)) {
            return false;
        }

        return null === $data
            || is_int($data)
            || is_float($data)
            || is_string($data)
            || is_bool($data);
    }

    /**
     * O(1) membership check against the precompiled scalar HashSet. Caller
     * must gate on `isScalarLookupEligible()` first.
     */
    public function contains(Schema $schema, mixed $data): bool
    {
        $set = $this->scalarSetFor($schema);

        return isset($set[$this->scalarKey($data)]);
    }

    private function isScalarEnum(Schema $schema): bool
    {
        if (isset($this->isScalarEnumCache[$schema])) {
            return (bool) $this->isScalarEnumCache[$schema];
        }

        $isScalar = $this->computeIsScalarEnum($schema->enum ?? []);
        $this->isScalarEnumCache[$schema] = $isScalar;

        return $isScalar;
    }

    /** @return array<string, true> */
    private function scalarSetFor(Schema $schema): array
    {
        if (isset($this->scalarEnumCache[$schema])) {
            /** @var array<string, true> $set */
            $set = $this->scalarEnumCache[$schema];

            return $set;
        }

        $set = $this->buildScalarSet($schema->enum ?? []);
        $this->scalarEnumCache[$schema] = $set;

        return $set;
    }

    /**
     * @param list<mixed> $enum
     */
    private function computeIsScalarEnum(array $enum): bool
    {
        /** @var mixed $value */
        foreach ($enum as $value) {
            if (null === $value || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<mixed> $enum
     *
     * @return array<string, true>
     */
    private function buildScalarSet(array $enum): array
    {
        $set = [];

        /** @var mixed $value */
        foreach ($enum as $value) {
            $set[$this->scalarKey($value)] = true;
        }

        return $set;
    }

    /**
     * Deterministic type-prefixed string key that mirrors `JsonEquals::equals`
     * semantics: int and float that are mathematically equal collapse to the
     * same key, while distinct JSON types stay disjoint. NaN always produces a
     * unique key per call so that two NaN values never compare equal per
     * JSON Schema §4.2.2.
     */
    private function scalarKey(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'b:' . ($value ? '1' : '0');
        }

        if (is_string($value)) {
            return 's:' . $value;
        }

        if (is_float($value) && is_nan($value)) {
            /** @var non-empty-string $bytes */
            $bytes = random_bytes(16);

            return 'nan:' . bin2hex($bytes);
        }

        /** @var int|float $value */
        return 'n:' . (string) (float) $value;
    }
}
