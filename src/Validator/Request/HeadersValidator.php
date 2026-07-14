<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class HeadersValidator extends AbstractParameterValidator
{
    public function __construct(
        protected readonly SchemaValidatorInterface $schemaValidator,
        protected readonly ParameterDeserializer $deserializer,
        protected readonly TypeCoercer $coercer,
        protected readonly ValidatorPool $pool = new ValidatorPool(),
        protected readonly bool $coercion = false,
        protected readonly ParameterValidationConfig $config = new ParameterValidationConfig(),
        private readonly HeaderFinder $headerFinder = new HeaderFinder(),
    ) {}

    #[Override]
    protected function getLocation(): string
    {
        return 'header';
    }

    #[Override]
    protected function findParameter(array $data, string $name): mixed
    {
        return $this->headerFinder->find($data, $name);
    }

    #[Override]
    protected function isRequired(Parameter $param, mixed $value): bool
    {
        return $param->required;
    }
}
