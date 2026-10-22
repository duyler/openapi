<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;

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
}
