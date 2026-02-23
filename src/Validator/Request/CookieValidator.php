<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Override;

use function count;
use function is_string;

readonly class CookieValidator extends AbstractParameterValidator
{
    public function parseCookies(string $cookieHeader): array
    {
        if ('' === trim($cookieHeader)) {
            return [];
        }

        $cookies = [];
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (2 === count($parts)) {
                $cookies[$parts[0]] = $parts[1];
            }
        }

        return $cookies;
    }

    public function parseCookieStyle(string $cookieHeader, Parameter $parameter): array|string|null
    {
        $name = $parameter->name ?? '';
        $style = $parameter->style ?? 'form';
        $explode = $parameter->explode;

        if ('cookie' !== $style && 'form' !== $style) {
            throw new InvalidParameterException(
                $name,
                "Cookie parameter style must be 'form' or 'cookie', got '{$style}'",
            );
        }

        if ('' === trim($cookieHeader)) {
            return null;
        }

        if ($explode && $this->hasMultipleCookies($cookieHeader, $name)) {
            return $this->parseExplodedValues($cookieHeader, $name);
        }

        $cookies = $this->parseCookies($cookieHeader);
        /** @var string|null $value */
        $value = $cookies[$name] ?? null;

        if (null === $value) {
            return null;
        }

        $decodedValue = $this->decodeValue($value);

        $schemaType = $parameter->schema?->type;
        if ('array' === $schemaType && str_contains($decodedValue, ',')) {
            return explode(',', $decodedValue);
        }

        return $decodedValue;
    }

    public function validateWithHeader(array $data, string $cookieHeader, array $parameterSchemas): void
    {
        foreach ($parameterSchemas as $param) {
            if (false === $param instanceof Parameter) {
                continue;
            }

            if ('cookie' !== $param->in) {
                continue;
            }

            $name = $param->name;
            if (null === $name) {
                continue;
            }

            if ('' !== trim($cookieHeader)) {
                $value = $this->parseCookieStyle($cookieHeader, $param);
            } else {
                /** @var mixed $value */
                $value = $this->findParameter($data, $name);
                if (is_string($value)) {
                    $value = $this->decodeValue($value);
                }
            }

            if (null === $value) {
                if ($this->isRequired($param, $value)) {
                    throw new MissingParameterException('cookie', $name);
                }
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);
            $value = $this->coercer->coerce($value, $param, $this->coercion, $this->coercion);

            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }

    #[Override]
    protected function getLocation(): string
    {
        return 'cookie';
    }

    #[Override]
    protected function findParameter(array $data, string $name): mixed
    {
        return $data[$name] ?? null;
    }

    private function parseExplodedValues(string $cookieHeader, string $name): array
    {
        $values = [];
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (2 === count($parts) && $parts[0] === $name) {
                $values[] = $this->decodeValue($parts[1]);
            }
        }

        return $values;
    }

    private function decodeValue(string $value): string
    {
        return rawurldecode($value);
    }

    private function hasMultipleCookies(string $cookieHeader, string $name): bool
    {
        $count = 0;
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (2 === count($parts) && $parts[0] === $name) {
                ++$count;
                if ($count > 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
