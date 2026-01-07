<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;

final readonly class ParameterDeserializer
{
    /**
     * Deserialize parameter value based on style
     */
    public function deserialize(mixed $value, Parameter $param): array|int|string|float|bool
    {
        $normalized = SchemaValueNormalizer::normalize($value);

        $style = $param->style ?? $this->getDefaultStyle($param->in);

        // Arrays are only valid for form style
        if (is_array($normalized)) {
            return 'form' === $style
                ? $this->deserializeForm($normalized, $param->explode)
                : $normalized;
        }

        /** @var string $normalized */
        return match ($style) {
            'matrix' => $this->deserializeMatrix($normalized, $param->name),
            'label' => $this->deserializeLabel($normalized),
            'simple' => $this->deserializeSimple($normalized),
            'form' => $this->deserializeForm($normalized, $param->explode),
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

    private function deserializeMatrix(string $value, string $name): string
    {
        // Remove the leading ;name= if present
        $prefix = ';' . $name . '=';
        if (str_starts_with($value, $prefix)) {
            return substr($value, strlen($prefix));
        }

        return $value;
    }

    private function deserializeLabel(string $value): string
    {
        // Remove the leading . if present
        if (str_starts_with($value, '.')) {
            return substr($value, 1);
        }

        return $value;
    }

    private function deserializeSimple(string $value): string
    {
        // For path parameters with simple style, keep as is
        // The simple style is used for path parameters and doesn't need deserialization
        return $value;
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

        return $value;
    }
}
