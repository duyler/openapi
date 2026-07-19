<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Override;

use function is_string;
use function rawurldecode;
use function sprintf;
use function substr_count;

final readonly class CookieValidator extends AbstractParameterValidator
{
    private const int MAX_COOKIE_PAIRS = 100;

    /**
     * Cookie pairs without `=` (e.g. flag-style cookies) are preserved with an
     * empty string value rather than dropped, for tolerant parsing of malformed
     * Cookie headers. Per RFC 6265 §4.1.1 cookie-pair should contain `=`, but
     * real-world clients sometimes send valueless cookies.
     *
     * @return array<string, string>
     */
    public function parseCookies(string $cookieHeader): array
    {
        if ('' === trim($cookieHeader)) {
            return [];
        }

        if (self::MAX_COOKIE_PAIRS < substr_count($cookieHeader, ';') + 1) {
            throw new InvalidParameterException(
                'cookie',
                sprintf('Maximum cookie pairs of %d exceeded', self::MAX_COOKIE_PAIRS),
            );
        }

        $cookies = [];
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ('' === $pair) {
                continue;
            }

            $equalsPos = strpos($pair, '=');

            if (false === $equalsPos) {
                $cookies[$pair] = '';
                continue;
            }

            $name = substr($pair, 0, $equalsPos);
            $value = substr($pair, $equalsPos + 1);
            $cookies[$name] = $value;
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

        $decodedValue = rawurldecode($value);

        $schemaType = $parameter->schema?->type;
        if ('array' === $schemaType && str_contains($decodedValue, ',')) {
            return explode(',', $decodedValue);
        }

        return $decodedValue;
    }

    public function validateWithHeader(array $data, string $cookieHeader, array $parameterSchemas): void
    {
        $context = ValidationContext::create(
            pool: $this->pool,
            nullableAsType: $this->config->nullableAsType,
            emptyArrayStrategy: $this->config->emptyArrayStrategy,
        );

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

            $value = $this->resolveValue($data, $cookieHeader, $name, $param);

            if (null === $value) {
                if ($this->isRequired($param, $value)) {
                    throw new MissingParameterException('cookie', $name);
                }
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);
            $value = $this->coercer->coerce($value, $param, $this->coercion, $this->config->strictCoercion);

            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema, $context);
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

    private function resolveValue(array $data, string $cookieHeader, string $name, Parameter $param): array|int|string|float|bool|null
    {
        if ('' !== trim($cookieHeader)) {
            return $this->parseCookieStyle($cookieHeader, $param);
        }

        /** @var array|int|string|float|bool|null $value */
        $value = $this->findParameter($data, $name);

        return is_string($value) ? rawurldecode($value) : $value;
    }

    private function parseExplodedValues(string $cookieHeader, string $name): array
    {
        if (self::MAX_COOKIE_PAIRS < substr_count($cookieHeader, ';') + 1) {
            throw new InvalidParameterException(
                'cookie',
                sprintf('Maximum cookie pairs of %d exceeded', self::MAX_COOKIE_PAIRS),
            );
        }

        $values = [];
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ('' === $pair) {
                continue;
            }

            $equalsPos = strpos($pair, '=');
            if (false === $equalsPos) {
                continue;
            }

            $pairName = substr($pair, 0, $equalsPos);
            if ($pairName === $name) {
                $values[] = rawurldecode(substr($pair, $equalsPos + 1));
            }
        }

        return $values;
    }

    private function hasMultipleCookies(string $cookieHeader, string $name): bool
    {
        if (self::MAX_COOKIE_PAIRS < substr_count($cookieHeader, ';') + 1) {
            throw new InvalidParameterException(
                'cookie',
                sprintf('Maximum cookie pairs of %d exceeded', self::MAX_COOKIE_PAIRS),
            );
        }

        $count = 0;
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ('' === $pair) {
                continue;
            }

            $equalsPos = strpos($pair, '=');
            if (false === $equalsPos) {
                continue;
            }

            if (substr($pair, 0, $equalsPos) === $name) {
                ++$count;
                if ($count > 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
