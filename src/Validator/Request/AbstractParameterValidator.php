<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use JsonException;

use function array_key_first;
use function is_array;
use function json_decode;
use function sprintf;
use function str_ends_with;

use const JSON_THROW_ON_ERROR;

abstract readonly class AbstractParameterValidator
{
    public function __construct(
        protected readonly SchemaValidatorInterface $schemaValidator,
        protected readonly ParameterDeserializer $deserializer,
        protected readonly TypeCoercer $coercer,
        protected readonly bool $coercion = false,
        protected readonly ValidatorPool $pool = new ValidatorPool(),
        protected readonly ParameterValidationConfig $config = new ParameterValidationConfig(),
    ) {}

    public function validate(array $data, array $parameterSchemas): void
    {
        $location = $this->getLocation();

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $this->pool,
            nullableAsType: $this->config->nullableAsType,
            emptyArrayStrategy: $this->config->emptyArrayStrategy,
            dialectProfile: $this->config->dialectProfile,
        ));

        foreach ($parameterSchemas as $param) {
            if (false === $param instanceof Parameter) {
                continue;
            }

            if ($location !== $param->in) {
                continue;
            }

            $name = $param->name;
            if (null === $name) {
                continue;
            }

            /** @var string|null $value */
            $value = $this->resolveValue($data, $param);

            if (null === $value) {
                if ($this->isRequired($param)) {
                    throw new MissingParameterException($location, $name);
                }
                continue;
            }

            if (null !== $param->content) {
                /** @var string|array $value */
                $this->validateContentValue($value, $param, $context);
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);
            $value = $this->coercer->coerce($value, $param, $this->coercion, $this->config->strictCoercion);

            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema, $context);
            }
        }
    }

    abstract protected function getLocation(): string;

    abstract protected function resolveValue(array $data, Parameter $param): mixed;

    protected function isRequired(Parameter $param): bool
    {
        return $param->required;
    }

    private function validateContentValue(string|array $value, Parameter $param, ValidationContext $context): void
    {
        $content = $param->content;

        if (null === $content) {
            return;
        }

        $mediaTypeKey = array_key_first($content->mediaTypes);

        if (null === $mediaTypeKey) {
            return;
        }

        $parsedValue = $this->parseContentValue($value, $mediaTypeKey, $param->name ?? '');

        $mediaTypeSchema = $content->mediaTypes[$mediaTypeKey]->schema;

        if (null !== $mediaTypeSchema) {
            $this->schemaValidator->validate($parsedValue, $mediaTypeSchema, $context);
        }
    }

    private function parseContentValue(string|array $value, string $mediaTypeKey, string $parameterName): array|string|int|float|bool|null
    {
        if (is_array($value)) {
            return $value;
        }

        if (false === $this->isJsonMediaType($mediaTypeKey)) {
            return $value;
        }

        try {
            /** @var array|int|string|float|bool|null $decoded */
            $decoded = json_decode($value, true, JsonDepthLimit::Untrusted->value, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException $e) {
            throw new InvalidParameterException(
                $parameterName,
                sprintf('Malformed JSON in content parameter: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    private function isJsonMediaType(string $mediaTypeKey): bool
    {
        return 'application/json' === $mediaTypeKey || str_ends_with($mediaTypeKey, '+json');
    }
}
