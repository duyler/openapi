<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ScalarKeyCodec;
use WeakMap;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final class EnumScalarCache
{
    private readonly ScalarKeyCodec $scalarKeyCodec;

    /** @var WeakMap<Schema, bool> */
    private WeakMap $isScalarEnumCache;

    /** @var WeakMap<Schema, array<string, true>> */
    private WeakMap $scalarEnumCache;

    public function __construct()
    {
        $this->scalarKeyCodec = new ScalarKeyCodec();

        /** @var WeakMap<Schema, bool> $isScalarEnumCache */
        $isScalarEnumCache = new WeakMap();
        $this->isScalarEnumCache = $isScalarEnumCache;

        /** @var WeakMap<Schema, array<string, true>> $scalarEnumCache */
        $scalarEnumCache = new WeakMap();
        $this->scalarEnumCache = $scalarEnumCache;
    }

    public function isScalarLookupEligible(Schema $schema, mixed $data): bool
    {
        if (false === $this->isScalarEnum($schema)) {
            return false;
        }

        return $this->isScalarOrNull($data);
    }

    public function contains(Schema $schema, mixed $data): bool
    {
        $set = $this->scalarSetFor($schema);

        return isset($set[$this->scalarKeyCodec->encode($data)]);
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
            if ($this->isScalarOrNull($value)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<mixed> $enum
     * @return array<string, true>
     */
    private function buildScalarSet(array $enum): array
    {
        $set = [];

        /** @var mixed $value */
        foreach ($enum as $value) {
            $set[$this->scalarKeyCodec->encode($value)] = true;
        }

        return $set;
    }

    private function isScalarOrNull(mixed $value): bool
    {
        return null === $value
            || is_int($value)
            || is_float($value)
            || is_string($value)
            || is_bool($value);
    }
}
