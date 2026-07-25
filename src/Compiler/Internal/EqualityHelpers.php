<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * Emits the inlined JSON-Schema §4.2.2 instance-equality helpers
 * (`jsonEquals`/`arraysEqual`) and the canonical-key helper
 * (`canonicalJsonKey`/`canonicalizeArrayKeys`) used by the compiled
 * validator for `enum`, `const`, and `uniqueItems`. Also answers whether
 * any of those keywords appear in the schema tree so the compiler can
 * skip emitting the helpers when they are unused.
 *
 * The emitted code mirrors `Duyler\OpenApi\Validator\Schema\JsonEquals`
 * byte-for-byte: integer/float equivalence (`1` == `1.0`), unordered
 * object keys, bool distinct from int, and the IEEE 754 boundary
 * `9007199254740992` (2^53) above which mixed int/float comparisons are
 * rejected as unequal.
 */
final readonly class EqualityHelpers
{
    public function isRequiredFor(Schema $schema): bool
    {
        if ($schema->hasConst || null !== $schema->enum || $schema->uniqueItems) {
            return true;
        }

        if ($schema->items instanceof Schema && $this->isRequiredFor($schema->items)) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $child) {
                if ($this->isRequiredFor($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderJsonEqualsInline(): string
    {
        return <<<'PHP'
    private function jsonEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return $a === $b;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            if (is_int($a) && is_int($b)) {
                return $a === $b;
            }
            if (is_int($a) && abs($a) > 9007199254740992) {
                return false;
            }
            if (is_int($b) && abs($b) > 9007199254740992) {
                return false;
            }
            return (float) $a === (float) $b;
        }
        if (is_array($a) && is_array($b)) {
            return $this->arraysEqual($a, $b);
        }
        return $a === $b;
    }

    private function arraysEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        if (array_is_list($a) && array_is_list($b)) {
            for ($i = 0, $n = count($a); $i < $n; ++$i) {
                if (!$this->jsonEquals($a[$i], $b[$i])) {
                    return false;
                }
            }
            return true;
        }
        foreach (array_keys($a) as $__key) {
            if (!array_key_exists($__key, $b)) {
                return false;
            }
            if (!$this->jsonEquals($a[$__key], $b[$__key])) {
                return false;
            }
        }
        return true;
    }

PHP;
    }

    public function renderCanonicalKeyInline(): string
    {
        return <<<'PHP'
    private function canonicalJsonKey(mixed $value): string
    {
        if (is_float($value) && (float) (int) $value === $value) {
            $value = (int) $value;
        }
        if (is_array($value)) {
            $value = $this->canonicalizeArrayKeys($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function canonicalizeArrayKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->canonicalizeArrayKeys($v);
            } elseif (is_float($v) && (float) (int) $v === $v) {
                $value[$k] = (int) $v;
            }
        }
        return $value;
    }

PHP;
    }
}
