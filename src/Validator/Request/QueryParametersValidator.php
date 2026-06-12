<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Override;

final readonly class QueryParametersValidator extends AbstractParameterValidator
{
    #[Override]
    protected function getLocation(): string
    {
        return 'query';
    }

    #[Override]
    protected function findParameter(array $data, string $name): mixed
    {
        return $data[$name] ?? null;
    }

    #[Override]
    protected function isRequired(Parameter $param, mixed $value): bool
    {
        return $param->required && false === $param->allowEmptyValue;
    }
}
