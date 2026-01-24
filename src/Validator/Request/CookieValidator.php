<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

use function count;

final readonly class CookieValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ParameterDeserializer $deserializer,
    ) {}

    /**
     * Parse Cookie header into array
     *
     * @return array<string, string>
     */
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

    /**
     * @param array<string, string> $cookies
     * @param array<int, Parameter> $parameterSchemas
     */
    public function validate(array $cookies, array $parameterSchemas): void
    {
        foreach ($parameterSchemas as $param) {
            if ('cookie' !== $param->in) {
                continue;
            }

            $name = $param->name;
            $value = $cookies[$name] ?? null;

            if (null === $value) {
                if ($param->required) {
                    throw new MissingParameterException('cookie', $name);
                }
                continue;
            }

            // Deserialize if needed
            $value = $this->deserializer->deserialize($value, $param);

            // Validate against schema
            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }
}
