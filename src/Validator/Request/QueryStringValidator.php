<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

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
    ) {}

    public function validate(string $queryString, array $parameterSchemas): void
    {
        foreach ($parameterSchemas as $param) {
            if (false === $param instanceof Parameter) {
                continue;
            }

            if ('querystring' !== $param->in) {
                continue;
            }

            $this->validateParameter($param);
            $this->validateQueryStringParameter($queryString, $param);
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

    private function validateQueryStringParameter(string $queryString, Parameter $parameter): void
    {
        $name = $parameter->name ?? 'unknown';

        if ('' === $queryString && $parameter->required) {
            throw new MissingParameterException('querystring', $name);
        }

        if ('' === $queryString) {
            return;
        }

        /** @var array<array-key, mixed>|scalar|null $parsedValue */
        $parsedValue = $this->queryParser->parseQueryString($queryString, $parameter);

        if (null === $parsedValue && $parameter->required) {
            throw new MissingParameterException('querystring', $name);
        }

        if (null === $parsedValue) {
            return;
        }

        $this->validateValueAgainstSchema($parsedValue, $parameter);
    }

    private function validateValueAgainstSchema(mixed $value, Parameter $parameter): void
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
                $this->schemaValidator->validate($value, $mediaTypeObject->schema);
            }
            return;
        }
    }
}
