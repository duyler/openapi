<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function is_array;
use function is_string;

/** @internal */
final readonly class CompositionTypeHelper
{
    /** @return list<array<string, list<string>>> */
    public static function asSecurityListMap(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected security list map, got ' . TypeFormatter::format($value));
        }

        $result = [];
        foreach ($value as $item) {
            if (false === is_array($item)) {
                throw new TypeError('Expected array in security list, got ' . TypeFormatter::format($item));
            }

            /** @var array<string, list<string>> $securityItem */
            $securityItem = [];
            foreach ($item as $key => $val) {
                if (false === is_string($key)) {
                    throw new TypeError('Expected string key in security map, got ' . TypeFormatter::format($key));
                }
                if (false === is_array($val)) {
                    throw new TypeError('Expected list in security map value, got ' . TypeFormatter::format($val));
                }
                /** @var list<string> $val */
                $val = ArrayTypeHelper::asStringList($val);
                $securityItem[$key] = $val;
            }
            $result[] = $securityItem;
        }

        return $result;
    }

    /** @return list<array<string, list<string>>>|null */
    public static function asSecurityListMapOrNull(mixed $value): ?array
    {
        return null === $value ? null : self::asSecurityListMap($value);
    }
}
