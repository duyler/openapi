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

final class EnumScalarCache
{
    /**
     * 2^53 — largest integer that survives a round-trip through IEEE 754
     * double without precision loss. Used to keep numeric equality (1 == 1.0)
     * while preventing distinct large int64 values from collapsing to the
     * same float key (SPEC-05).
     */
    private const int SAFE_INT64_FLOAT_BOUNDARY = 9007199254740992;

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
            /** @var array<string, true> */
            return $this->scalarEnumCache[$schema];
        }

        $set = $this->buildScalarSet($schema->enum ?? []);
        $this->scalarEnumCache[$schema] = $set;

        return $set;
    }

    /** @param list<mixed> $enum */
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

        if (is_int($value)) {
            // SPEC-05: large int64 values lose precision when cast to float
            // (9223372036854775806 and 9223372036854775807 both become
            // 9.223372036854776E+18). 2^53 is the IEEE 754 boundary: every
            // int with abs <= 2^53 round-trips through float unchanged, so
            // emit the float form to keep numeric equality (1 == 1.0). Above
            // the boundary emit the int directly so distinct int64 enum
            // values do not collapse and we avoid a lossy float→int cast on
            // PHP 8.5.
            if (abs($value) <= self::SAFE_INT64_FLOAT_BOUNDARY) {
                return 'n:' . (string) (float) $value;
            }

            return 'n:i:' . (string) $value;
        }

        // Remaining scalar case: float (callers gate via isScalarLookupEligible).
        /** @var float $value */
        return 'n:' . (string) $value;
    }
}
