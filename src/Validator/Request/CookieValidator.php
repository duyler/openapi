<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Request\Internal\CookieHeaderParser;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function count;
use function explode;
use function is_string;
use function rawurldecode;
use function sprintf;
use function str_contains;
use function trim;

final readonly class CookieValidator extends AbstractParameterValidator
{
    public function __construct(
        SchemaValidatorInterface $schemaValidator,
        ParameterDeserializer $deserializer,
        TypeCoercer $coercer,
        bool $coercion = false,
        ValidatorPool $pool = new ValidatorPool(),
        ParameterValidationConfig $config = new ParameterValidationConfig(),
        private readonly CookieHeaderParser $cookieHeaderParser = new CookieHeaderParser(),
        private readonly ?string $cookieHeader = null,
        /**
         * @var array<string, string> $cookies
         */
        private readonly array $cookies = [],
    ) {
        parent::__construct($schemaValidator, $deserializer, $coercer, $coercion, $pool, $config);
    }

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
        return $this->cookieHeaderParser->parsePairs($cookieHeader);
    }

    public function parseCookieStyle(string $cookieHeader, Parameter $parameter): array|string|null
    {
        return $this->parseCookieStyleWith($cookieHeader, $parameter, $this->parseCookies($cookieHeader));
    }

    public function validateWithHeader(array $data, string $cookieHeader, array $parameterSchemas): void
    {
        $validator = '' !== trim($cookieHeader)
            ? $this->withCookieHeader($cookieHeader)
            : $this;

        $validator->validate($data, $parameterSchemas);
    }

    #[Override]
    protected function getLocation(): string
    {
        return 'cookie';
    }

    #[Override]
    protected function resolveValue(array $data, Parameter $param): mixed
    {
        if (null === $this->cookieHeader) {
            return $this->resolveValueFromData($data, $param);
        }

        return $this->parseCookieStyleWith($this->cookieHeader, $param, $this->cookies);
    }

    /**
     * @param array<string, string> $cookies
     */
    private function parseCookieStyleWith(string $cookieHeader, Parameter $parameter, array $cookies): array|string|null
    {
        $name = $parameter->name ?? '';
        $style = $parameter->style ?? 'form';

        if ('cookie' !== $style && 'form' !== $style) {
            throw new InvalidParameterException(
                $name,
                sprintf("Cookie parameter style must be 'form' or 'cookie', got '%s'", $style),
            );
        }

        if ('' === trim($cookieHeader)) {
            return null;
        }

        if ($parameter->explode) {
            $values = $this->cookieHeaderParser->parseExplodedValues($cookieHeader, $name);

            if (1 < count($values)) {
                return $values;
            }
        }

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

    private function resolveValueFromData(array $data, Parameter $param): array|int|string|float|bool|null
    {
        /** @var array|int|string|float|bool|null $value */
        $value = $data[$param->name ?? ''] ?? null;

        return is_string($value) ? rawurldecode($value) : $value;
    }

    private function withCookieHeader(string $cookieHeader): self
    {
        return new self(
            schemaValidator: $this->schemaValidator,
            deserializer: $this->deserializer,
            coercer: $this->coercer,
            coercion: $this->coercion,
            pool: $this->pool,
            config: $this->config,
            cookieHeaderParser: $this->cookieHeaderParser,
            cookieHeader: $cookieHeader,
            cookies: $this->cookieHeaderParser->parsePairs($cookieHeader),
        );
    }
}
