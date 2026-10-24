<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Coercion\Internal\BooleanCoercer;
use Duyler\OpenApi\Validator\Coercion\Internal\IntegerCoercer;
use Duyler\OpenApi\Validator\Coercion\NumberStringNormalizer;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use JsonException;

use stdClass;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_numeric;
use function json_decode;
use function sprintf;
use function substr_count;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class PartHeaderValueValidator
{
    private const int MAX_HEADER_ARRAY_ITEMS = 1000;

    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ValidatorPool $pool = new ValidatorPool(),
        private readonly ParameterValidationConfig $config = new ParameterValidationConfig(),
        private readonly IntegerCoercer $integerCoercer = new IntegerCoercer(),
        private readonly BooleanCoercer $booleanCoercer = new BooleanCoercer(),
    ) {}

    public function validate(string $name, string $value, Header $header): void
    {
        ByteRangePartParser::parse(headerName: $name, headerValue: $value);

        $schema = $header->schema ?? $this->contentSchema($header);

        if (null === $schema) {
            return;
        }

        $coercedValue = $this->coerceValue($value, $schema, $name);

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $this->pool,
            nullableAsType: $this->config->nullableAsType,
            emptyArrayStrategy: $this->config->emptyArrayStrategy,
            dialectProfile: $this->config->dialectProfile,
        ));

        $this->schemaValidator->validate($coercedValue, $schema, $context);
    }

    private function contentSchema(Header $header): ?Schema
    {
        $content = $header->content;

        if (null === $content) {
            return null;
        }

        foreach ($content->mediaTypes as $mediaType) {
            if (null !== $mediaType->schema) {
                return $mediaType->schema;
            }
        }

        return null;
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
        $coerced = $this->integerCoercer->coerceStringStrict($value);

        if (null === $coerced) {
            throw new TypeMismatchError(
                expected: 'integer',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '/type',
            );
        }

        return $coerced;
    }

    private function coerceToNumber(string $value, string $headerName): float
    {
        if (false === is_numeric($value)) {
            throw new TypeMismatchError(
                expected: 'number',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '/type',
            );
        }

        return NumberStringNormalizer::castStringToFloatOrFail($value, $headerName);
    }

    private function coerceToBoolean(string $value, string $headerName): bool
    {
        $coerced = $this->booleanCoercer->coerceStringStrict($value);

        if (null === $coerced) {
            throw new TypeMismatchError(
                expected: 'boolean',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '/type',
            );
        }

        return $coerced;
    }

    private function coerceToArray(string $value, string $headerName): array
    {
        if (substr_count($value, ',') + 1 > self::MAX_HEADER_ARRAY_ITEMS) {
            throw new InvalidParameterException(
                $headerName,
                sprintf('Maximum array items of %d exceeded', self::MAX_HEADER_ARRAY_ITEMS),
            );
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
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
                schemaPath: '/type',
            );
        }

        if (false === $decoded instanceof stdClass) {
            throw new TypeMismatchError(
                expected: 'object',
                actual: $value,
                dataPath: $headerName,
                schemaPath: '/type',
            );
        }

        return (array) $decoded;
    }
}
