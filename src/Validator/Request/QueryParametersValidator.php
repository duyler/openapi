<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

final readonly class QueryParametersValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ParameterDeserializer $deserializer,
    ) {}

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<int, Parameter> $parameterSchemas
     */
    public function validate(array $queryParams, array $parameterSchemas): void
    {
        foreach ($parameterSchemas as $param) {
            if ('query' !== $param->in) {
                continue;
            }

            $name = $param->name;
            $value = $queryParams[$name] ?? null;

            if (null === $value) {
                if ($param->required && false === $param->allowEmptyValue) {
                    throw new MissingParameterException('query', $name);
                }
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);

            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }
}
