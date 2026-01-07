<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

final readonly class ResponseHeadersValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
    ) {}

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     */
    public function validate(array $headers, ?Headers $headerSchemas): void
    {
        if (null === $headerSchemas) {
            return;
        }

        foreach ($headerSchemas->headers as $name => $header) {
            $value = $this->findHeader($headers, $name);

            if (null === $value && $header->required) {
                throw new MissingParameterException('header', $name);
            }

            if (null !== $value && null !== $header->schema) {
                $this->schemaValidator->validate($value, $header->schema);
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
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }

        return null;
    }
}
