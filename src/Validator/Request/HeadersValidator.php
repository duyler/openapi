<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

final readonly class HeadersValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
    ) {}

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     * @param array<int, Parameter> $headerSchemas
     */
    public function validate(array $headers, array $headerSchemas): void
    {
        foreach ($headerSchemas as $param) {
            if ('header' !== $param->in) {
                continue;
            }

            $name = $param->name;
            $value = $this->findHeader($headers, $name);

            if (null === $value && $param->required) {
                throw new MissingParameterException('header', $name);
            }

            if (null !== $value && null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     */
    private function findHeader(array $headers, string $name): ?string
    {
        // Case-insensitive search
        foreach ($headers as $key => $value) {
            if (false === is_string($key)) {
                continue;
            }
            if (strtolower($key) === strtolower($name)) {
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value;
            }
        }

        return null;
    }
}
