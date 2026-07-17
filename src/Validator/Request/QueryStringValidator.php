<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;

use function assert;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class QueryStringValidator
{
    public function __construct(
        private readonly QueryParser $queryParser,
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ValidatorPool $pool = new ValidatorPool(),
        private readonly ParameterValidationConfig $config = new ParameterValidationConfig(),
    ) {}

    public function validate(string $queryString, array $parameterSchemas): void
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

            if ('querystring' !== $param->in) {
                continue;
            }

            $this->validateParameter($param);
            $this->validateQueryStringParameter($queryString, $param, $context);
        }
    }

    public function validateParameter(Parameter $parameter): void
    {
        $name = $parameter->name ?? 'unknown';

        if (null !== $parameter->schema) {
            throw new InvalidParameterException(
                $name,
                "Parameter with 'querystring' location must use 'content' field, not 'schema'",
            );
        }

        if (null === $parameter->content || [] === $parameter->content->mediaTypes) {
            throw new InvalidParameterException(
                $name,
                "Parameter with 'querystring' location requires 'content' field",
            );
        }
    }

    private function validateQueryStringParameter(string $queryString, Parameter $parameter, ValidationContext $context): void
    {
        if ('' === $queryString && $parameter->required) {
            throw new MissingParameterException('querystring', $parameter->name ?? 'unknown');
        }

        if ('' === $queryString) {
            return;
        }

        /** @var array<array-key, mixed>|scalar|null $parsedValue */
        $parsedValue = $this->queryParser->parseQueryString($queryString, $parameter);
        $this->validateValueAgainstSchema($parsedValue, $parameter, $context);
    }

    private function validateValueAgainstSchema(mixed $value, Parameter $parameter, ValidationContext $context): void
    {
        $content = $parameter->content;
        if (null === $content) {
            return;
        }

        foreach ($content->mediaTypes as $mediaTypeObject) {
            if (null !== $mediaTypeObject->schema) {
                assert(
                    is_array($value)
                    || is_int($value)
                    || is_string($value)
                    || is_float($value)
                    || is_bool($value)
                    || null === $value,
                );
                $this->schemaValidator->validate($value, $mediaTypeObject->schema, $context);
            }
            return;
        }
    }
}
