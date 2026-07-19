<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;

use function array_map;
use function assert;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;
use function in_array;
use function substr_count;
use function trim;

final readonly class ParameterDeserializer
{
    private const int MAX_PARAMETER_ARRAY_ITEMS = 1000;

    public function deserialize(mixed $value, Parameter $param): array|int|string|float|bool
    {
        $normalized = SchemaValueNormalizer::normalize($value);

        assert(null !== $param->in && null !== $param->name, 'Parameter in and name must not be null when deserialize is called');

        $style = $param->style ?? $this->getDefaultStyle($param->in);

        if (is_array($normalized)) {
            return 'form' === $style
                ? $this->deserializeForm($normalized, $param->explode)
                : $normalized;
        }

        /** @var string $normalized */
        return match ($style) {
            'matrix' => $this->deserializeMatrix($normalized, $param),
            'label' => $this->deserializeLabel($normalized, $param),
            'simple' => $this->deserializeSimple($normalized, $param),
            'form' => $this->deserializeForm($normalized, $param->explode),
            'pipeDelimited' => $this->deserializePipeDelimited($normalized),
            'spaceDelimited' => $this->deserializeSpaceDelimited($normalized),
            'cookie' => $this->deserializeCookie($normalized, $param),
            'deepObject' => $this->deserializeDeepObject($normalized),
            default => $normalized,
        };
    }

    private function getDefaultStyle(string $in): string
    {
        return match ($in) {
            'path' => 'simple',
            'query' => 'form',
            'header' => 'simple',
            'cookie' => 'form',
            default => 'form',
        };
    }

    private function deserializeMatrix(string $value, Parameter $param): array|string
    {
        $name = $param->name ?? '';
        $prefix = ';' . $name . '=';

        $stripped = str_starts_with($value, $prefix)
            ? substr($value, strlen($prefix))
            : $value;

        if (false === $this->isArrayType($param)) {
            return $stripped;
        }

        $separator = $param->explode ? $prefix : ',';

        return $this->splitBySeparator($stripped, $separator);
    }

    private function deserializeLabel(string $value, Parameter $param): array|string
    {
        $stripped = str_starts_with($value, '.')
            ? substr($value, 1)
            : $value;

        if (false === $this->isArrayType($param)) {
            return $stripped;
        }

        $separator = $param->explode ? '.' : ',';

        return $this->splitBySeparator($stripped, $separator);
    }

    private function deserializeSimple(string $value, Parameter $param): array|string
    {
        if (false === $this->isArrayType($param)) {
            return $value;
        }

        return $this->splitBySeparator($value, ',');
    }

    private function deserializeForm(array|string $value, bool $explode): array|int|string
    {
        if (is_array($value)) {
            if ($explode) {
                return $value;
            }

            /** @var array<int, scalar> $value */
            return implode(',', $value);
        }

        if (false === $explode && str_contains($value, ',')) {
            if (substr_count($value, ',') + 1 > self::MAX_PARAMETER_ARRAY_ITEMS) {
                throw new InvalidParameterException(
                    'parameter',
                    sprintf('Maximum array items of %d exceeded', self::MAX_PARAMETER_ARRAY_ITEMS),
                );
            }

            return explode(',', $value);
        }

        return $value;
    }

    private function deserializePipeDelimited(string $value): array
    {
        if (substr_count($value, '|') + 1 > self::MAX_PARAMETER_ARRAY_ITEMS) {
            throw new InvalidParameterException(
                'parameter',
                sprintf('Maximum array items of %d exceeded', self::MAX_PARAMETER_ARRAY_ITEMS),
            );
        }

        return explode('|', $value);
    }

    private function deserializeSpaceDelimited(string $value): array
    {
        if (substr_count($value, ' ') + 1 > self::MAX_PARAMETER_ARRAY_ITEMS) {
            throw new InvalidParameterException(
                'parameter',
                sprintf('Maximum array items of %d exceeded', self::MAX_PARAMETER_ARRAY_ITEMS),
            );
        }

        return explode(' ', $value);
    }

    private function deserializeCookie(string $value, Parameter $param): array|string
    {
        if (false === $this->isArrayType($param)) {
            return $value;
        }

        return $this->splitBySeparator($value, ',');
    }

    /** @return list<string> */
    private function deserializeDeepObject(string $value): array
    {
        return [$value];
    }

    private function isArrayType(Parameter $param): bool
    {
        $type = $param->schema?->type;

        if (is_string($type)) {
            return 'array' === $type;
        }

        return is_array($type) && in_array('array', $type, true);
    }

    /** @return list<string> */
    private function splitBySeparator(string $value, string $separator): array
    {
        assert('' !== $separator, 'Separator must not be empty');

        if ('' === $value) {
            return [];
        }

        if (str_contains($value, $separator)) {
            if (substr_count($value, $separator) + 1 > self::MAX_PARAMETER_ARRAY_ITEMS) {
                throw new InvalidParameterException(
                    'parameter',
                    sprintf('Maximum array items of %d exceeded', self::MAX_PARAMETER_ARRAY_ITEMS),
                );
            }

            /** @var list<string> $parts */
            $parts = explode($separator, $value);

            return array_map(trim(...), $parts);
        }

        return [$value];
    }
}
