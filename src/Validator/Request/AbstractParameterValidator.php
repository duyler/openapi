<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

abstract readonly class AbstractParameterValidator
{
    public function __construct(
        protected readonly SchemaValidatorInterface $schemaValidator,
        protected readonly ParameterDeserializer $deserializer,
        protected readonly TypeCoercer $coercer,
        protected readonly bool $coercion = false,
    ) {}

    public function validate(array $data, array $parameterSchemas): void
    {
        $location = $this->getLocation();

        foreach ($parameterSchemas as $param) {
            if (!$param instanceof Parameter) {
                continue;
            }

            if ($param->in !== $location) {
                continue;
            }

            $name = $param->name;
            if (null === $name) {
                continue;
            }

            $value = $this->findParameter($data, $name);

            if (null === $value) {
                if ($this->isRequired($param, $value)) {
                    throw new MissingParameterException($location, $name);
                }
                continue;
            }

            $value = $this->deserializer->deserialize($value, $param);
            $value = $this->coercer->coerce($value, $param, $this->coercion, $this->coercion);

            if (null !== $param->schema) {
                $this->schemaValidator->validate($value, $param->schema);
            }
        }
    }

    abstract protected function getLocation(): string;

    abstract protected function findParameter(array $data, string $name): mixed;

    protected function isRequired(Parameter $param, mixed $value): bool
    {
        return $param->required;
    }
}
