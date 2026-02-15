<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\HeaderFinder;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

use function array_filter;
use function array_map;
use function floatval;
use function in_array;
use function intval;
use function is_numeric;
use function strtolower;

readonly class ResponseHeadersValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly HeaderFinder $headerFinder = new HeaderFinder(),
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
            $value = $this->headerFinder->find($headers, $name);

            if (null === $value && $header->required) {
                throw new MissingParameterException('header', $name);
            }

            if (null !== $value && null !== $header->schema) {
                $coercedValue = $this->coerceValue($value, $header->schema, $name);
                $this->schemaValidator->validate($coercedValue, $header->schema);
            }
        }
    }

    private function coerceValue(string $value, Schema $schema, string $headerName): array|int|string|float|bool
    {
        $type = $schema->type;

        if ('string' === $type) {
            return $value;
        }

        if ('integer' === $type) {
            return $this->coerceToInteger($value, $headerName);
        }

        if ('number' === $type) {
            return $this->coerceToNumber($value, $headerName);
        }

        if ('boolean' === $type) {
            return $this->coerceToBoolean($value, $headerName);
        }

        if ('array' === $type) {
            return $this->coerceToArray($value, $headerName);
        }

        return $value;
    }

    private function coerceToInteger(string $value, string $headerName): int
    {
        if (false === is_numeric($value)) {
            throw new TypeMismatchError(
                'integer',
                'string',
                $headerName,
                '#/type',
            );
        }

        return intval($value);
    }

    private function coerceToNumber(string $value, string $headerName): float
    {
        if (false === is_numeric($value)) {
            throw new TypeMismatchError(
                'number',
                'string',
                $headerName,
                '#/type',
            );
        }

        return floatval($value);
    }

    private function coerceToBoolean(string $value, string $headerName): bool
    {
        $lowerValue = strtolower($value);

        $trueValues = ['true', '1', 'yes', 'on'];
        $falseValues = ['false', '0', 'no', 'off'];

        if (in_array($lowerValue, $trueValues, true)) {
            return true;
        }

        if (in_array($lowerValue, $falseValues, true)) {
            return false;
        }

        return (bool) $value;
    }

    private function coerceToArray(string $value, string $headerName): array
    {
        $items = array_filter(array_map(trim(...), explode(',', $value)));

        return array_values($items);
    }
}
