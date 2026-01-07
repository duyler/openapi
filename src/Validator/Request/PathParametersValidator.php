<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

final readonly class PathParametersValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly ParameterDeserializer $deserializer,
    ) {}

    /**
     * @param array<string, mixed> $params Parameter values from path
     * @param array<int, Parameter> $parameterSchemas OpenAPI parameter definitions
     */
    public function validate(array $params, array $parameterSchemas): void
    {
        foreach ($parameterSchemas as $param) {
            if ('path' !== $param->in) {
                continue;
            }

            $name = $param->name;
            $value = $params[$name] ?? null;

            if (null === $value) {
                if ($param->required) {
                    throw new MissingParameterException('path', $name);
                }
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);

            // Validate against schema
            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }
}
