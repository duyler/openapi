<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Override;

final readonly class PathParametersValidator extends AbstractParameterValidator
{
    #[Override]
    protected function getLocation(): string
    {
        return 'path';
    }

    #[Override]
    protected function resolveValue(array $data, Parameter $param): mixed
    {
        return $data[$param->name ?? ''] ?? null;
    }
}
