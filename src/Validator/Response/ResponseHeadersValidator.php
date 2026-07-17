<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Coercion\IntegerStringNormalizer;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Request\HeaderFinder;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use JsonException;

use stdClass;

use function array_filter;
use function array_map;
use function array_values;
use function floatval;
use function in_array;
use function json_decode;
use function sprintf;
use function substr_count;

use function strtolower;

use const JSON_THROW_ON_ERROR;

final readonly class ResponseHeadersValidator
{
    private const array TRUTHY_VALUES = ['true', '1', 'yes', 'on'];
    private const array FALSY_VALUES = ['false', '0', 'no', 'off'];

    private const int MAX_HEADER_ARRAY_ITEMS = 1000;

    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ValidatorPool $pool = new ValidatorPool(),
        private readonly ParameterValidationConfig $config = new ParameterValidationConfig(),
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
                $context = ValidationContext::create(
                    pool: $this->pool,
                    nullableAsType: $this->config->nullableAsType,
                    emptyArrayStrategy: $this->config->emptyArrayStrategy,
                );
                $this->schemaValidator->validate($coercedValue, $header->schema, $context);
            }
        }
    }

    private function coerceValue(string $value, Schema $schema, string $headerName): array|int|string|float|bool
    {
        return match ($schema->type) {
            'integer' => $this->coerceToInteger($value, $headerName),
            'number' => $this->coerceToNumber($value, $headerName),
            'boolean' => $this->coerceToBoolean($value, $headerName),
            'array' => $this->coerceToArray($value, $headerName),
            'object' => $this->coerceToObject($value, $headerName),
            default => $value,
        };
    }

    private function coerceToInteger(string $value, string $headerName): int
    {
        if (1 !== preg_match('/^[+-]?\d+$/', $value)) {
            throw new TypeMismatchError(
                expected: 'integer',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '#/type',
            );
        }

        $coerced = (int) $value;

        if ((string) $coerced !== IntegerStringNormalizer::canonicalize($value)) {
            throw new TypeMismatchError(
                expected: 'integer',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '#/type',
            );
        }

        return $coerced;
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

        if (in_array($lowerValue, self::TRUTHY_VALUES, true)) {
            return true;
        }

        if (in_array($lowerValue, self::FALSY_VALUES, true)) {
            return false;
        }

        throw new TypeMismatchError(
            expected: 'boolean',
            actual: 'string',
            dataPath: $headerName,
            schemaPath: '#/type',
        );
    }

    private function coerceToArray(string $value, string $headerName): array
    {
        if (substr_count($value, ',') + 1 > self::MAX_HEADER_ARRAY_ITEMS) {
            throw new InvalidParameterException(
                $headerName,
                sprintf('Maximum array items of %d exceeded', self::MAX_HEADER_ARRAY_ITEMS),
            );
        }

        $items = array_filter(array_map(trim(...), explode(',', $value)));

        return array_values($items);
    }

    private function coerceToObject(string $value, string $headerName): array
    {
        if ('' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, false, JsonDepthLimit::Untrusted->value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new TypeMismatchError(
                expected: 'object',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '#/type',
            );
        }

        if (!$decoded instanceof stdClass) {
            throw new TypeMismatchError(
                expected: 'object',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '#/type',
            );
        }

        return (array) $decoded;
    }
}
